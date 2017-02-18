<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Document\Imagery,
    AppBundle\Helper\StringHelper;
use Aws\S3\S3Client;
use GuzzleHttp\Client;

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
        $this->setName('goesir:daily:download')
                ->setDescription('Download new files from GOES')
                ->addArgument('localstorage', InputArgument::OPTIONAL, 'Optional local storage folder');
    }


    
    
    protected function execute(InputInterface $input, OutputInterface $output) {

        $start = date('c');
        $output->writeln('********************* START *****************************');
        $output->writeln('INFO: started ' . $start);

        /***************************************
         *       MONGODB DOCUMENT MANAGER
         ***************************************/
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        if ($this->dm == null) {
            $output->writeln('ERROR: Unable to connect document manager');
            $output->writeln('********************* END *******************************');
            return;
        }
        $output->writeln('INFO: Document Manager listo');



        /******************************************
         * LOCAL STORAGE IN CASE IT IS NEEDED
         ******************************************/
        $localStorage = $input->getArgument('localstorage');
        if ($localStorage != null) {
            if (!is_writable($localStorage)) {
                $output->writeln('ERROR: Specified local storage "' . $localStorage . '" is NOT writable ');
                $output->writeln('********************* END *******************************');
                return;
            }
        }

        /******************************************
         *         AMAZON S3 CLIENT
         ******************************************/
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
        $stored = [];
        $skipped = [];
        foreach ($this->validFiles as $validFile) {
            /* @var $imagery Imagery */
            $imagery = $this->dm->getRepository('AppBundle:Imagery')->getByFilenameAndDate($validFile->file, $validFile->date);
            if ($imagery == null) {
                $im = new Imagery();
                $im->setDated($validFile->date);
                $im->setImageName($validFile->file);
                $im->setOriginalDate($validFile->dateoriginal);
                $this->dm->persist($im);
                $stored[]=$im;
            } else {
                $skipped[] = $validFile;
            }
        }
        $this->dm->flush();
        $output->writeln('INFO: New metadata files for download: ' . count($stored));

        /* @var $file Imagery */
        foreach ($stored as $file) {
            $output->writeln('INFO: NEW FILE ' . $file->getImageName() . ' ' . $file->getOriginalDate());
        }
        $output->writeln('INFO: Skipped files: ' . count($skipped));


        // now download the pending ones and store them in amazon
        $pending = $this->dm->getRepository('AppBundle:Imagery')->getDownloadPending();

        $output->writeln('INFO: Download pending files: ');


        // hydrate all the results!!!!
        
        foreach ($pending as $imagery) {
            $output->writeln('INFO: Pending file: '.$imagery->getImageName().' '.$imagery->getOriginalDate().' '.$imagery->getId());
        }
        
        
        
        /*********************************
         *      LOOP THRU PENDING
         *********************************/
        /* @var $imagery Imagery */
        foreach ($pending as $imagery) {

            $this->dm->persist($imagery);

            $output->write('INFO: Start copying ' . $imagery->getImageName() . ' ' . $imagery->getOriginalDate());

            $key = self::S3_FOLDER . '/' . $imagery->getImageName();
            $URL = self::GOES_BASE . self::GOES_FOLDER . $imagery->getImageName();
            $body = $this->curl_get_file_contents($URL);

            if ($body === FALSE) {
                // unable to read from URL
                $output->writeln('ERROR: unable to download from GOES');
                continue;
            }
            if ($body === 404) {
                // remove this, not found
                $this->dm->remove($imagery);
                $this->dm->flush($imagery);
                $output->writeln('ERROR: file not found. Removing.');
                continue;
            }
            if (strlen($body) < 100000) {
                // remove this, file truncated
                $this->dm->remove($imagery);
                $this->dm->flush($imagery);
                $output->writeln('ERROR: file too small. Removing.');
                continue;
            }

            
            // Copy the remote file to our Amazon S3 bucket
            try {
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
                $imagery->setFileSize($result['filesize']);
                $imagery->setDownloadDate(new \MongoDate());
                $imagery->setStored(true);
                $imagery->setStorage($result['ObjectURL']);
                $output->writeln(' OK. ' . strlen($body) . ' bytes.');
            } 
            catch (\Exception $ex) {
                $output->writeln('ERROR: ' . $ex->getMessage());
            }
            $this->dm->flush();
        } // end forach pendinf

        $this->dm->flush();
        $output->writeln('********************* END *******************************');
    }

    
    
    /**
     * Downloads remote file using cURL
     * @param type $URL
     * @return boolean|int
     */
    private function curl_get_file_contents($URL) {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        curl_close($c);

        if ($contents) {

            if (StringHelper::contains($contents, '404 Not Found')) {
                return 404;
            }
            return $contents;
        } else {
            return FALSE;
        }
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
