<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Document\Imagery,
    AppBundle\Helper\StringHelper;
use Aws\S3\S3Client;
use Doctrine\Common\Collections\ArrayCollection;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use RuntimeException;

class DownloadCommand extends ContainerAwareCommand {

    private $dm;

    /* @var $S3Client S3Client */
    private $S3Client;
    private $validFiles = []; // 1702170238G13I02.tif is https://goes.gsfc.nasa.gov/goeseast/argentina/ir2/1702070245G13I02.tif

    const GOES_BASE = 'https://goes.gsfc.nasa.gov';
    const GOES_FOLDER = '/goeseast/argentina/ir2/';
    const S3_BUCKET = 'goesir';
    const S3_FOLDER = 'raw';

    protected function configure() {
        $this
                ->setName('goesir:daily:download')
                ->setDescription('')
                ->addArgument('localstorage', InputArgument::OPTIONAL, 'Optional local storage folder');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $start = date('c');

        $output->writeln('********************* START *****************************');
        $output->writeln('INFO: started ' . $start);


        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        if ($this->dm == null) {
            $output->writeln('ERROR: Unable to connect document manager');
            $output->writeln('********************* END *******************************');
            return;
        }
        $output->writeln('INFO: Document Manager listo');

        $localStorage = $input->getArgument('localstorage');
        if ($localStorage != null) {
            if (!is_writable($localStorage)) {
                $output->writeln('ERROR: Specified local storage "' . $localStorage . '" is NOT writable ');
                $output->writeln('********************* END *******************************');
                return;
            }
        }

        // Amazon
        $connected = true;
        $failMessage = 'none';
        try {
            $this->S3Client = S3Client::factory(
                            array(
                                'region' => $this->getContainer()->getParameter('amazon_s3.region'),
                                'credentials' => array(
                                    'key' => $this->getContainer()->getParameter('amazon_s3.key'),
                                    'secret' => $this->getContainer()->getParameter('amazon_s3.secret'),
                                ),
                                'version' => 'latest'
            ));
        } catch (\Exception $e) {
            $failMessage = $e->getMessage();
            $connected = false;
        }
        if (!$connected) {
            $output->writeln('ERROR: imposible conectar a Amazon. ' . $failMessage);
            $output->writeln('********************* END *******************************');
            return;
        }

        // load the raw HTML from GOES website
        $html = $this->getHTML();

        // parse files and dates
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $trs = $dom->getElementsByTagName("tr");
        for ($i = 0; $i < $trs->length; $i++) {
            $this->parseFileAndDate($trs->item($i)->getElementsbyTagName("td"));
        }

        // check for new files
        $persist = $this->persistNewFiles();
        $output->writeln('INFO: Stored metadata files for download: ' . count($persist['stored']));
        foreach ($persist['stored'] as $stored) {
            $output->writeln('INFO: ' . $stored->file . ' ' . $stored->dateoriginal);
        }
        $output->writeln('INFO: Skipped files: ' . count($persist['skipped']));


        // now download the pending ones and store them in amazon
        $pending = $this->dm->getRepository('AppBundle:Imagery')->getDownloadPending();


        $output->writeln('INFO: Download pending files: ');
        /* @var $imagery Imagery */
        foreach ($pending as $imagery) {
            $output->write('INFO: Start copying ' . $imagery->getImageName());

            // Copy the remote file to our Amazon S3 bucket
            try {
                $result = $this->putRemote($imagery, $localStorage);
                
                // partial files...
                
                if($result['filesize']<100000) {
                    $output->writeln(' WARNING: '.$result['filesize'].' bytes. Will re-download during next run.');
                }
                else {
                    $output->writeln(' OK. '.$result['filesize'].' bytes.');
                    $this->dm->persist($imagery);
                    $imagery->setDownloadDate(new \MongoDate());
                    $imagery->setStored(true);
                    $imagery->setStorage($result['ObjectURL']);
                    $imagery->setFileSize($result['filesize']);
                    $this->dm->flush();
                }
            } catch (\Exception $ex) {
                $output->writeln('ERROR: ' . $ex->getMessage());
            }
        }

        $this->dm->flush();


        $output->writeln('********************* END *******************************');
    }

    function curl_get_file_contents($URL) {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        curl_close($c);

        if ($contents) {
            return $contents;
        } else {
            return FALSE;
        }
    }

    /**
     * 
     * @param Imagery $imagery
     * @return type
     * @throws Exception
     */
    private function putRemote(Imagery $imagery, $localStorage = null) {

        try {
            $key = self::S3_FOLDER . '/' . $imagery->getImageName();
            $URL=self::GOES_BASE . self::GOES_FOLDER . $imagery->getImageName();
            $body = $this->curl_get_file_contents($URL);

            if($body===FALSE) {
                throw new \Exception('ERROR: Falla al descargar de URL ' . $URL);
            }
            
            if ($localStorage != null) {
                file_put_contents($localStorage . '/' . $imagery->getImageName(), $body);
            }
            $result = $this->S3Client->putObject(array(
                'ACL' => 'public-read',
                'Body' => $body,
                'Bucket' => self::S3_BUCKET,
                'ContentLength' => strlen($body),
                'Key' => $key,
                'Metadata' => array(
                    'originaldate' => $imagery->getOriginalDate(),
                ),
            ));
            $result['filesize']=  strlen($body);
        } 
        catch (\Exception $e) {
            throw new \Exception('ERROR: Falla al subir archivo ' . $imagery->getImageName() . '=> ' . $e->getMessage());
        }
        
        return $result;
    }

    /**
     * Persists for later download (queue) verifying we do not already have the same file in the db
     * @return array with results
     */
    private function persistNewFiles() {
        $stored = [];
        $skipped = [];
        foreach ($this->validFiles as $validFile) {
            /* @var $imagery Imagery */
            $imagery = $this->dm->getRepository('AppBundle:Imagery')->getByFilenameAndDate($validFile->file, $validFile->date);
            if ($imagery == null) {
                $stored[] = $validFile;
                $im = new Imagery();
                $im->setDated($validFile->date);
                $im->setImageName($validFile->file);
                $im->setOriginalDate($validFile->dateoriginal);
                $this->dm->persist($im);
            } else {
                $skipped[] = $validFile;
            }
        }
        $this->dm->flush();
        return array('stored' => $stored, 'skipped' => $skipped);
    }

    /**
     * Parses the file info from the TRs on the GOES listing table
     * @param DOMElementlist $tds
     */
    private function parseFileAndDate($tds) {
        if ($tds->length > 3) {
            $file = $tds->item(1)->nodeValue;
            $date = $tds->item(2)->nodeValue;
            if (StringHelper::endsWith($file, '.tif') && strlen($file) == 20) {
                $parseDate = new \DateTime($date);
                $obj = new \stdClass();
                $obj->file = $file;
                $obj->date = new \MongoDate($parseDate->getTimestamp());
                $obj->dateoriginal = $date;
                $this->validFiles[] = $obj;
            }
        }
    }

    private function getHTML() {

//        $html = <<<EOT
//</body></html>
//                
//EOT;
        // produtcion:
        $guzzleClient = new Client(array('base_uri' => self::GOES_BASE));
        $response = $guzzleClient->get(self::GOES_FOLDER);
        $html = $response->getBody()->getContents();

        return $html;
    }

}
