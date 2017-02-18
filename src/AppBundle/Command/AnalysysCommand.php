<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Document\Imagery,
    AppBundle\Document\Analysys,
    AppBundle\Helper\StringHelper;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Imagick;
use ImagickPixel;

class AnalysysCommand extends ContainerAwareCommand {

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
                ->setName('goesir:analysys:brightness')
                ->setDescription('Calculatres brightness of every pixel in the image')
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
            if (!is_readable($localStorage)) {
                $output->writeln('ERROR: Specified local storage "' . $localStorage . '" is NOT readable');
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

        // now download the pending ones and store them in amazon
        $pending = $this->dm->getRepository('AppBundle:Imagery')->getAnalysysPending();


        $output->writeln('INFO: Performing brightness analysis.');
        $sampleo=[];
        
        /* @var $imagery Imagery */
        foreach ($pending as $imagery) {
            // load the image
            $image = $this->getImagickFromDocument($imagery, $localStorage);

            // get array for brighntesses
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();

            //$output->writeln('INFO: File ' . $imagery->getImageName() . "width: {$width} height: {$height}");
            $pixel = $image->getImagePixelColor(512, 331);
            $data = $this->formatPixelValues($pixel);
            $rgb=(65536 * $data['colors']['r'] + 256 * $data['colors']['g'] + $data['colors']['b']);
            $sampleo[]=$rgb;
            //$output->writeln('INFO: File ' . $imagery->getImageName() . "val: {$rgb}");
            $output->writeln($rgb);

//            for ($x = 0; $x < $width; $x++) {
//                for ($y = 0; $y < $height; $y++) {
//                    /* @var $pixel \ImagickPixel */
//                    $pixel = $image->getImagePixelColor($x, $y);
//                    $data = $this->formatPixelValues($pixel);
//                    $a = new Analysys();
//                    $a->setImagery($imagery);
//                    $a->setPxred($data['colors']['r']);
//                    $a->setPxgreen($data['colors']['g']);
//                    $a->setPxblue($data['colors']['b']);
//                    $a->setAlpha($data['colors']['a']);
//                    $a->setPxrednormal($data['red']);
//                    $a->setPxgreennormal($data['green']);
//                    $a->setPxbluenormal($data['blue']);
//                    $a->setPxcyan($data['cyan']);
//                    $a->setPxmagenta($data['magenta']);
//                    $a->setPxyellow($data['yellow']);
//                    $a->setPxblack($data['black']);
//                    $a->setPxtotal(65536 * $data['colors']['r'] + 256 * $data['colors']['g'] + $data['colors']['b']);
//                    $a->setPixelx($x);
//                    $a->setPixely($y);
//                    
//                    if($x==512 && $y==331) {
//                        $sampleo[]=$a->getPxtotal();
//                    }
//                    
//                    //$this->dm->persist($a);
//                }
//                $output->write(" {$x}");
//            }
//            //$imagery->setAnalyzed(true);
//            $this->dm->flush();
        }
        //$this->dm->flush();
        $output->writeln(json_encode($sampleo));
        $output->writeln('********************* END *******************************');
    }

    private function formatPixelValues(\ImagickPixel $pixel) {
        $ret = [];
        $ret['colors'] = $pixel->getColor();
        $ret['red'] = $pixel->getColorValue(Imagick::COLOR_RED);
        $ret['green'] = $pixel->getColorValue(Imagick::COLOR_GREEN);
        $ret['blue'] = $pixel->getColorValue(Imagick::COLOR_BLUE);
        $ret['cyan'] = $pixel->getColorValue(Imagick::COLOR_CYAN);
        $ret['magenta'] = $pixel->getColorValue(Imagick::COLOR_MAGENTA);
        $ret['yellow'] = $pixel->getColorValue(Imagick::COLOR_YELLOW);
        $ret['black'] = $pixel->getColorValue(Imagick::COLOR_BLACK);
        return $ret;
    }

    /**
     * 
     * @param Imagery $imagery
     * @param type $localStorage
     * @return \Imagick
     * @throws \Exception
     */
    private function getImagickFromDocument(Imagery $imagery, $localStorage = null) {
        if ($localStorage != null) {
            $image = new \Imagick($localStorage . '/' . $imagery->getImageName());
        } else {
            // download from Amazon
            try {
                $key = self::S3_FOLDER . '/' . $imagery->getImageName();
                $result = $this->S3Client->getObject(array(
                    'Bucket' => self::S3_BUCKET,
                    'Key' => $key
                ));

                $image = new \Imagick();
                $image->readImageBlob($result['Body']);
            } catch (\Exception $e) {
                throw new \Exception('ERROR: Unable to crerate Imagick object frpm S3 bucket key ' . $key . '=> ' . $e->getMessage());
            }
        }

        return $image;
    }

}
