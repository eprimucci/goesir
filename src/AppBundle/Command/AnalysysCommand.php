<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Document\Imagery,
    AppBundle\Document\Pixel,
    AppBundle\Document\PixelData,
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
                ->addArgument('x', InputArgument::REQUIRED, 'X coordinate')
                ->addArgument('y', InputArgument::REQUIRED, 'Y coordinate')
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

        $coordX = $input->getArgument('x');
        $coordY = $input->getArgument('y');
        if ($coordX < 0 || $coordX > 999 || $coordY < 0 || $coordY > 599) {
            $output->writeln('ERROR: Coordinates out of bounds (X=>0-999 Y=>0-599)');
            $output->writeln('********************* END *******************************');
            return;
        }


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
        } 
        catch (\Exception $e) {
            $failMessage = $e->getMessage();
            $connected = false;
        }
        if (!$connected) {
            $output->writeln('ERROR: imposible conectar a Amazon. ' . $failMessage);
            $output->writeln('********************* END *******************************');
            return;
        }

        $pixel = $this->dm->getRepository('AppBundle:Pixel')->findByCoordinates($coordX, $coordY);
        if ($pixel == null) {
            $pixel = new Pixel();
            $pixel->setPixelx($coordX);
            $pixel->setPixely($coordY);
            $this->dm->persist($pixel);
            $this->dm->flush();
        }

        // get available images
        $availableImagery = $this->dm->getRepository('AppBundle:Imagery')->findByAnalysysPending();

        $output->writeln("INFO: Performing brightness analysis for {$coordX},{$coordY}");

        /* @var $imagery Imagery */
        foreach ($availableImagery as $imagery) {

            if ($pixel->hasImagery($imagery->getId())) {
                continue;
            }

            $output->writeln("INFO: Imagery name:{$imagery->getImageName()} odate:{$imagery->getOriginalDate()} id:{$imagery->getId()}");
            
            // load the image
            $image = $this->getImagickFromDocument($imagery, $localStorage);

            $imagickPixelData = $image->getImagePixelColor($coordX, $coordY);
            $data = $this->formatPixelValues($imagickPixelData);
            $rgb = (65536 * $data['colors']['r'] + 256 * $data['colors']['g'] + $data['colors']['b']);

            
            
            $pixelData = new PixelData();
            $pixelData->setImageryid($imagery->getId());
            $pixelData->setPxred($data['colors']['r']);
            $pixelData->setPxgreen($data['colors']['g']);
            $pixelData->setPxblue($data['colors']['b']);
            $pixelData->setAlpha($data['colors']['a']);
            $pixelData->setPxrednormal($data['red']);
            $pixelData->setPxgreennormal($data['green']);
            $pixelData->setPxbluenormal($data['blue']);
            $pixelData->setPxcyan($data['cyan']);
            $pixelData->setPxmagenta($data['magenta']);
            $pixelData->setPxyellow($data['yellow']);
            $pixelData->setPxblack($data['black']);
            $pixelData->setPxtotal($rgb);
            $pixelData->setTs($imagery->getDated()->getTimestamp());
            $pixel->addPixelData($pixelData);
        }
        
        $this->dm->flush();
        
        /* @var $existingPixelData PixelData */
        foreach($pixel->getPixeldatum() as $existingPixelData) {
            $date = new \DateTime();
            $date->setTimestamp($existingPixelData->getTs());
            $output->writeln($existingPixelData->getPxtotal().','.$existingPixelData->getTs().',"'.$date->format('Y-m-d H:i:s').'"');
        }
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
                throw new \Exception('ERROR: Unable to crerate Imagick object frpm S3 bucket key ' . self::S3_FOLDER . '/' . $imagery->getImageName() . '=> ' . $e->getMessage());
            }
        }

        return $image;
    }

}
