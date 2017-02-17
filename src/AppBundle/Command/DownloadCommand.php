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

    /* @var $client S3Client */
    private $client;
    private $validFiles = []; // 1702170238G13I02.tif is https://goes.gsfc.nasa.gov/goeseast/argentina/ir2/1702070245G13I02.tif
    
    CONST GOES_BASE = 'https://goes.gsfc.nasa.gov';
    const GOES_FOLDER = '/goeseast/argentina/ir2/';

    protected function configure() {
        $this
                ->setName('goesir:daily:download')
                ->setDescription('');
        //->addArgument('configURL', InputArgument::REQUIRED, 'Especificar URL de archivo de configuración');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $start = date('c');

        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        if ($this->dm == null) {
            $output->writeln('ERROR: Unable to connect document manager');
            return;
        }
        $output->writeln('**********************************************************');
        $output->writeln('INFO: Document Manager listo');


        // Amazon
//        $connected=true;
//        $failMessage='none';
//        try {
//            $this->client = S3Client::factory(
//                    array(
//                        'key' => $this->getContainer()->getParameter('amazon_s3.key'), 
//                        'secret' => $this->getContainer()->getParameter('amazon_s3.secret'), 
//                        'region'=>$this->getContainer()->getParameter('amazon_s3.region'),
//                        'version'=>'2006-03-01'
//                        ));
//        }
//        catch(\Exception $e) {
//            $failMessage=$e->getMessage();
//            $connected=false;
//        }
//        if(!$connected) {
//            $output->writeln('ERROR: imposible conectar a Amazon. '.$failMessage);
//            return;
//        }
        // create http client instance

        $html = $this->getHTML();

        // parse files and dates
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $trs = $dom->getElementsByTagName("tr");
        for ($i = 0; $i < $trs->length; $i++) {
            $this->parseFileAndDate($trs->item($i)->getElementsbyTagName("td"));
        }
        
        // check for new files
        $persist=$this->persistNewFiles();
        $output->writeln('INFO: Stored metadata files for download: '.count($persist['stored']));
        foreach($persist['stored'] as $stored) {
            $output->writeln('INFO: '.$stored->file.' '.$stored->dateoriginal);
        }
        $output->writeln('INFO: Skipped files: '.count($persist['skipped']));

        
        // now download the pending ones and store them in amazon
        $pending = $this->dm->getRepository('AppBundle:Imagery')->getDownloadPending();

        
        $output->writeln('INFO: Download pending files: ');
        /* @var $imagery Imagery */
        foreach($pending as $imagery) {
            $output->writeln($imagery->getImageName());
        }
        
        $this->dm->flush();
        
        
        $output->writeln('**********************************************************');
    }

    
    
    
    


    /**
     * Persists for later download (queue) verifying we do not already have the same file in the db
     * @return array with results
     */
    private function persistNewFiles() {
        $stored=[]; $skipped=[];
        foreach ($this->validFiles as $validFile) {
            /* @var $imagery Imagery */
            $imagery = $this->dm->getRepository('AppBundle:Imagery')->getByFilenameAndDate($validFile->file, $validFile->date);
            if ($imagery == null) {
                $stored[]=$validFile;
                $im=new Imagery();
                $im->setDated($validFile->date);
                $im->setImageName($validFile->file);
                $im->setOriginalDate($validFile->dateoriginal);
                $this->dm->persist($im);
            }
            else {
                $skipped[]=$validFile;
            }
        }
        $this->dm->flush();
        return array('stored'=>$stored, 'skipped'=>$skipped);
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

        $html = <<<EOT
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html>
 <head>
  <title>Index of /goeseast/argentina/ir2</title>
 </head>
 <body>
<h1>Index of /goeseast/argentina/ir2</h1>
<table><tr><th><img src="/icons/blank.gif" alt="[ICO]"></th><th><a href="?C=N;O=D">Name</a></th><th><a href="?C=M;O=A">Last modified</a></th><th><a href="?C=S;O=A">Size</a></th><th><a href="?C=D;O=A">Description</a></th></tr><tr><th colspan="5"><hr></th></tr>
<tr><td valign="top"><img src="/icons/back.gif" alt="[DIR]"></td><td><a href="/goeseast/argentina/">Parent Directory</a></td><td>&nbsp;</td><td align="right">  - </td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070245G13I02.tif">1702070245G13I02.tif</a></td><td align="right">06-Feb-2017 22:21  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070338G13I02.tif">1702070338G13I02.tif</a></td><td align="right">06-Feb-2017 22:48  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070408G13I02.tif">1702070408G13I02.tif</a></td><td align="right">06-Feb-2017 23:18  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070438G13I02.tif">1702070438G13I02.tif</a></td><td align="right">06-Feb-2017 23:48  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070508G13I02.tif">1702070508G13I02.tif</a></td><td align="right">07-Feb-2017 00:16  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070538G13I02.tif">1702070538G13I02.tif</a></td><td align="right">07-Feb-2017 00:47  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070545G13I02.tif">1702070545G13I02.tif</a></td><td align="right">07-Feb-2017 01:25  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070638G13I02.tif">1702070638G13I02.tif</a></td><td align="right">07-Feb-2017 01:48  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070708G13I02.tif">1702070708G13I02.tif</a></td><td align="right">07-Feb-2017 02:18  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070738G13I02.tif">1702070738G13I02.tif</a></td><td align="right">07-Feb-2017 02:48  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070808G13I02.tif">1702070808G13I02.tif</a></td><td align="right">07-Feb-2017 03:18  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070838G13I02.tif">1702070838G13I02.tif</a></td><td align="right">07-Feb-2017 03:47  </td><td align="right">350K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070845G13I02.tif">1702070845G13I02.tif</a></td><td align="right">07-Feb-2017 04:26  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702070938G13I02.tif">1702070938G13I02.tif</a></td><td align="right">07-Feb-2017 04:48  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071008G13I02.tif">1702071008G13I02.tif</a></td><td align="right">07-Feb-2017 05:18  </td><td align="right">342K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071038G13I02.tif">1702071038G13I02.tif</a></td><td align="right">07-Feb-2017 05:47  </td><td align="right">335K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071108G13I02.tif">1702071108G13I02.tif</a></td><td align="right">07-Feb-2017 06:18  </td><td align="right">330K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071138G13I02.tif">1702071138G13I02.tif</a></td><td align="right">07-Feb-2017 06:47  </td><td align="right">325K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071145G13I02.tif">1702071145G13I02.tif</a></td><td align="right">07-Feb-2017 07:24  </td><td align="right">362K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071238G13I02.tif">1702071238G13I02.tif</a></td><td align="right">07-Feb-2017 07:48  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071308G13I02.tif">1702071308G13I02.tif</a></td><td align="right">07-Feb-2017 08:18  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071338G13I02.tif">1702071338G13I02.tif</a></td><td align="right">07-Feb-2017 08:47  </td><td align="right">356K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071408G13I02.tif">1702071408G13I02.tif</a></td><td align="right">07-Feb-2017 09:17  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071438G13I02.tif">1702071438G13I02.tif</a></td><td align="right">07-Feb-2017 09:57  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071445G13I02.tif">1702071445G13I02.tif</a></td><td align="right">07-Feb-2017 10:21  </td><td align="right">419K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071605G13I02.tif">1702071605G13I02.tif</a></td><td align="right">07-Feb-2017 11:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071635G13I02.tif">1702071635G13I02.tif</a></td><td align="right">07-Feb-2017 11:39  </td><td align="right">299K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071705G13I02.tif">1702071705G13I02.tif</a></td><td align="right">07-Feb-2017 12:08  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071735G13I02.tif">1702071735G13I02.tif</a></td><td align="right">07-Feb-2017 12:39  </td><td align="right">299K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071745G13I02.tif">1702071745G13I02.tif</a></td><td align="right">07-Feb-2017 13:20  </td><td align="right">432K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071835G13I02.tif">1702071835G13I02.tif</a></td><td align="right">07-Feb-2017 13:39  </td><td align="right">290K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071905G13I02.tif">1702071905G13I02.tif</a></td><td align="right">07-Feb-2017 14:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702071935G13I02.tif">1702071935G13I02.tif</a></td><td align="right">07-Feb-2017 14:39  </td><td align="right">279K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072005G13I02.tif">1702072005G13I02.tif</a></td><td align="right">07-Feb-2017 15:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072035G13I02.tif">1702072035G13I02.tif</a></td><td align="right">07-Feb-2017 15:39  </td><td align="right">269K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072045G13I02.tif">1702072045G13I02.tif</a></td><td align="right">07-Feb-2017 16:20  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072135G13I02.tif">1702072135G13I02.tif</a></td><td align="right">07-Feb-2017 16:39  </td><td align="right">260K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072205G13I02.tif">1702072205G13I02.tif</a></td><td align="right">07-Feb-2017 17:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072235G13I02.tif">1702072235G13I02.tif</a></td><td align="right">07-Feb-2017 17:39  </td><td align="right">259K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072305G13I02.tif">1702072305G13I02.tif</a></td><td align="right">07-Feb-2017 18:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072335G13I02.tif">1702072335G13I02.tif</a></td><td align="right">07-Feb-2017 18:39  </td><td align="right">261K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702072345G13I02.tif">1702072345G13I02.tif</a></td><td align="right">07-Feb-2017 19:21  </td><td align="right">385K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080105G13I02.tif">1702080105G13I02.tif</a></td><td align="right">07-Feb-2017 20:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080135G13I02.tif">1702080135G13I02.tif</a></td><td align="right">07-Feb-2017 20:39  </td><td align="right">264K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080205G13I02.tif">1702080205G13I02.tif</a></td><td align="right">07-Feb-2017 21:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080235G13I02.tif">1702080235G13I02.tif</a></td><td align="right">07-Feb-2017 21:38  </td><td align="right">264K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080245G13I02.tif">1702080245G13I02.tif</a></td><td align="right">07-Feb-2017 22:21  </td><td align="right">383K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080335G13I02.tif">1702080335G13I02.tif</a></td><td align="right">07-Feb-2017 22:39  </td><td align="right">259K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080405G13I02.tif">1702080405G13I02.tif</a></td><td align="right">07-Feb-2017 23:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080435G13I02.tif">1702080435G13I02.tif</a></td><td align="right">07-Feb-2017 23:38  </td><td align="right">258K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080505G13I02.tif">1702080505G13I02.tif</a></td><td align="right">08-Feb-2017 00:09  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080535G13I02.tif">1702080535G13I02.tif</a></td><td align="right">08-Feb-2017 00:39  </td><td align="right">260K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080545G13I02.tif">1702080545G13I02.tif</a></td><td align="right">08-Feb-2017 01:21  </td><td align="right">387K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080638G13I02.tif">1702080638G13I02.tif</a></td><td align="right">08-Feb-2017 01:48  </td><td align="right">354K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080708G13I02.tif">1702080708G13I02.tif</a></td><td align="right">08-Feb-2017 02:17  </td><td align="right">357K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080738G13I02.tif">1702080738G13I02.tif</a></td><td align="right">08-Feb-2017 02:47  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080808G13I02.tif">1702080808G13I02.tif</a></td><td align="right">08-Feb-2017 03:17  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080838G13I02.tif">1702080838G13I02.tif</a></td><td align="right">08-Feb-2017 03:47  </td><td align="right">365K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080845G13I02.tif">1702080845G13I02.tif</a></td><td align="right">08-Feb-2017 04:21  </td><td align="right">403K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702080938G13I02.tif">1702080938G13I02.tif</a></td><td align="right">08-Feb-2017 04:47  </td><td align="right">357K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081008G13I02.tif">1702081008G13I02.tif</a></td><td align="right">08-Feb-2017 05:17  </td><td align="right">349K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081038G13I02.tif">1702081038G13I02.tif</a></td><td align="right">08-Feb-2017 05:47  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081108G13I02.tif">1702081108G13I02.tif</a></td><td align="right">08-Feb-2017 06:17  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081138G13I02.tif">1702081138G13I02.tif</a></td><td align="right">08-Feb-2017 06:47  </td><td align="right">328K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081145G13I02.tif">1702081145G13I02.tif</a></td><td align="right">08-Feb-2017 07:20  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081238G13I02.tif">1702081238G13I02.tif</a></td><td align="right">08-Feb-2017 07:46  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081308G13I02.tif">1702081308G13I02.tif</a></td><td align="right">08-Feb-2017 08:17  </td><td align="right">343K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081338G13I02.tif">1702081338G13I02.tif</a></td><td align="right">08-Feb-2017 08:47  </td><td align="right">353K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081408G13I02.tif">1702081408G13I02.tif</a></td><td align="right">08-Feb-2017 09:17  </td><td align="right">362K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081438G13I02.tif">1702081438G13I02.tif</a></td><td align="right">08-Feb-2017 09:47  </td><td align="right">370K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081445G13I02.tif">1702081445G13I02.tif</a></td><td align="right">08-Feb-2017 10:19  </td><td align="right">417K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081608G13I02.tif">1702081608G13I02.tif</a></td><td align="right">08-Feb-2017 11:17  </td><td align="right">386K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081638G13I02.tif">1702081638G13I02.tif</a></td><td align="right">08-Feb-2017 11:47  </td><td align="right">390K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081738G13I02.tif">1702081738G13I02.tif</a></td><td align="right">08-Feb-2017 12:46  </td><td align="right">392K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081745G13I02.tif">1702081745G13I02.tif</a></td><td align="right">08-Feb-2017 13:19  </td><td align="right">433K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081838G13I02.tif">1702081838G13I02.tif</a></td><td align="right">08-Feb-2017 13:47  </td><td align="right">385K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081908G13I02.tif">1702081908G13I02.tif</a></td><td align="right">08-Feb-2017 14:17  </td><td align="right">380K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702081938G13I02.tif">1702081938G13I02.tif</a></td><td align="right">08-Feb-2017 14:47  </td><td align="right">376K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082008G13I02.tif">1702082008G13I02.tif</a></td><td align="right">08-Feb-2017 15:17  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082038G13I02.tif">1702082038G13I02.tif</a></td><td align="right">08-Feb-2017 15:47  </td><td align="right">371K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082045G13I02.tif">1702082045G13I02.tif</a></td><td align="right">08-Feb-2017 16:20  </td><td align="right">407K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082138G13I02.tif">1702082138G13I02.tif</a></td><td align="right">08-Feb-2017 16:47  </td><td align="right">365K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082208G13I02.tif">1702082208G13I02.tif</a></td><td align="right">08-Feb-2017 17:17  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082238G13I02.tif">1702082238G13I02.tif</a></td><td align="right">08-Feb-2017 17:47  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082308G13I02.tif">1702082308G13I02.tif</a></td><td align="right">08-Feb-2017 18:17  </td><td align="right">362K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082338G13I02.tif">1702082338G13I02.tif</a></td><td align="right">08-Feb-2017 18:46  </td><td align="right">365K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702082345G13I02.tif">1702082345G13I02.tif</a></td><td align="right">08-Feb-2017 19:21  </td><td align="right">404K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090038G13I02.tif">1702090038G13I02.tif</a></td><td align="right">08-Feb-2017 19:47  </td><td align="right">369K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090108G13I02.tif">1702090108G13I02.tif</a></td><td align="right">08-Feb-2017 20:17  </td><td align="right">367K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090138G13I02.tif">1702090138G13I02.tif</a></td><td align="right">08-Feb-2017 20:47  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090208G13I02.tif">1702090208G13I02.tif</a></td><td align="right">08-Feb-2017 21:18  </td><td align="right">364K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090238G13I02.tif">1702090238G13I02.tif</a></td><td align="right">08-Feb-2017 21:47  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090245G13I02.tif">1702090245G13I02.tif</a></td><td align="right">08-Feb-2017 22:21  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090338G13I02.tif">1702090338G13I02.tif</a></td><td align="right">08-Feb-2017 22:47  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090408G13I02.tif">1702090408G13I02.tif</a></td><td align="right">08-Feb-2017 23:17  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090438G13I02.tif">1702090438G13I02.tif</a></td><td align="right">08-Feb-2017 23:47  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090508G13I02.tif">1702090508G13I02.tif</a></td><td align="right">09-Feb-2017 00:15  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090538G13I02.tif">1702090538G13I02.tif</a></td><td align="right">09-Feb-2017 00:47  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090545G13I02.tif">1702090545G13I02.tif</a></td><td align="right">09-Feb-2017 01:21  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090638G13I02.tif">1702090638G13I02.tif</a></td><td align="right">09-Feb-2017 01:48  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090708G13I02.tif">1702090708G13I02.tif</a></td><td align="right">09-Feb-2017 02:17  </td><td align="right">365K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090738G13I02.tif">1702090738G13I02.tif</a></td><td align="right">09-Feb-2017 02:47  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090808G13I02.tif">1702090808G13I02.tif</a></td><td align="right">09-Feb-2017 03:17  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090838G13I02.tif">1702090838G13I02.tif</a></td><td align="right">09-Feb-2017 03:46  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090845G13I02.tif">1702090845G13I02.tif</a></td><td align="right">09-Feb-2017 04:21  </td><td align="right">406K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702090938G13I02.tif">1702090938G13I02.tif</a></td><td align="right">09-Feb-2017 04:47  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091008G13I02.tif">1702091008G13I02.tif</a></td><td align="right">09-Feb-2017 05:17  </td><td align="right">355K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091038G13I02.tif">1702091038G13I02.tif</a></td><td align="right">09-Feb-2017 05:47  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091108G13I02.tif">1702091108G13I02.tif</a></td><td align="right">09-Feb-2017 06:17  </td><td align="right">339K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091138G13I02.tif">1702091138G13I02.tif</a></td><td align="right">09-Feb-2017 06:47  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091445G13I02.tif">1702091445G13I02.tif</a></td><td align="right">09-Feb-2017 10:59  </td><td align="right">421K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091608G13I02.tif">1702091608G13I02.tif</a></td><td align="right">09-Feb-2017 11:18  </td><td align="right">392K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091638G13I02.tif">1702091638G13I02.tif</a></td><td align="right">09-Feb-2017 11:47  </td><td align="right">397K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091708G13I02.tif">1702091708G13I02.tif</a></td><td align="right">09-Feb-2017 12:15  </td><td align="right">402K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091738G13I02.tif">1702091738G13I02.tif</a></td><td align="right">09-Feb-2017 12:46  </td><td align="right">403K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091745G13I02.tif">1702091745G13I02.tif</a></td><td align="right">09-Feb-2017 13:20  </td><td align="right">442K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091838G13I02.tif">1702091838G13I02.tif</a></td><td align="right">09-Feb-2017 13:47  </td><td align="right">394K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091908G13I02.tif">1702091908G13I02.tif</a></td><td align="right">09-Feb-2017 14:17  </td><td align="right">386K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702091938G13I02.tif">1702091938G13I02.tif</a></td><td align="right">09-Feb-2017 14:47  </td><td align="right">382K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092008G13I02.tif">1702092008G13I02.tif</a></td><td align="right">09-Feb-2017 15:17  </td><td align="right">378K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092038G13I02.tif">1702092038G13I02.tif</a></td><td align="right">09-Feb-2017 15:47  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092045G13I02.tif">1702092045G13I02.tif</a></td><td align="right">09-Feb-2017 16:20  </td><td align="right">407K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092138G13I02.tif">1702092138G13I02.tif</a></td><td align="right">09-Feb-2017 16:47  </td><td align="right">364K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092208G13I02.tif">1702092208G13I02.tif</a></td><td align="right">09-Feb-2017 17:17  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092238G13I02.tif">1702092238G13I02.tif</a></td><td align="right">09-Feb-2017 17:47  </td><td align="right">356K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092308G13I02.tif">1702092308G13I02.tif</a></td><td align="right">09-Feb-2017 18:17  </td><td align="right">353K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092338G13I02.tif">1702092338G13I02.tif</a></td><td align="right">09-Feb-2017 18:47  </td><td align="right">354K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702092345G13I02.tif">1702092345G13I02.tif</a></td><td align="right">09-Feb-2017 19:21  </td><td align="right">392K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100038G13I02.tif">1702100038G13I02.tif</a></td><td align="right">09-Feb-2017 19:47  </td><td align="right">357K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100108G13I02.tif">1702100108G13I02.tif</a></td><td align="right">09-Feb-2017 20:17  </td><td align="right">356K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100138G13I02.tif">1702100138G13I02.tif</a></td><td align="right">09-Feb-2017 20:48  </td><td align="right">356K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100208G13I02.tif">1702100208G13I02.tif</a></td><td align="right">09-Feb-2017 21:17  </td><td align="right">354K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100238G13I02.tif">1702100238G13I02.tif</a></td><td align="right">09-Feb-2017 21:46  </td><td align="right">352K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100245G13I02.tif">1702100245G13I02.tif</a></td><td align="right">09-Feb-2017 22:21  </td><td align="right">384K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100338G13I02.tif">1702100338G13I02.tif</a></td><td align="right">09-Feb-2017 22:47  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100408G13I02.tif">1702100408G13I02.tif</a></td><td align="right">09-Feb-2017 23:17  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100438G13I02.tif">1702100438G13I02.tif</a></td><td align="right">09-Feb-2017 23:47  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100508G13I02.tif">1702100508G13I02.tif</a></td><td align="right">10-Feb-2017 00:16  </td><td align="right">343K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100538G13I02.tif">1702100538G13I02.tif</a></td><td align="right">10-Feb-2017 00:47  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100545G13I02.tif">1702100545G13I02.tif</a></td><td align="right">10-Feb-2017 01:21  </td><td align="right">385K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100638G13I02.tif">1702100638G13I02.tif</a></td><td align="right">10-Feb-2017 01:47  </td><td align="right">354K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100708G13I02.tif">1702100708G13I02.tif</a></td><td align="right">10-Feb-2017 02:17  </td><td align="right">358K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100738G13I02.tif">1702100738G13I02.tif</a></td><td align="right">10-Feb-2017 02:47  </td><td align="right">358K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100808G13I02.tif">1702100808G13I02.tif</a></td><td align="right">10-Feb-2017 03:17  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100838G13I02.tif">1702100838G13I02.tif</a></td><td align="right">10-Feb-2017 03:47  </td><td align="right">359K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100845G13I02.tif">1702100845G13I02.tif</a></td><td align="right">10-Feb-2017 04:21  </td><td align="right">398K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702100938G13I02.tif">1702100938G13I02.tif</a></td><td align="right">10-Feb-2017 04:47  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101008G13I02.tif">1702101008G13I02.tif</a></td><td align="right">10-Feb-2017 05:17  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101038G13I02.tif">1702101038G13I02.tif</a></td><td align="right">10-Feb-2017 05:47  </td><td align="right">355K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101108G13I02.tif">1702101108G13I02.tif</a></td><td align="right">10-Feb-2017 06:16  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101138G13I02.tif">1702101138G13I02.tif</a></td><td align="right">10-Feb-2017 06:46  </td><td align="right">342K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101338G13I02.tif">1702101338G13I02.tif</a></td><td align="right">10-Feb-2017 09:42  </td><td align="right">362K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101408G13I02.tif">1702101408G13I02.tif</a></td><td align="right">10-Feb-2017 09:51  </td><td align="right">369K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101445G13I02.tif">1702101445G13I02.tif</a></td><td align="right">10-Feb-2017 11:16  </td><td align="right">421K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101608G13I02.tif">1702101608G13I02.tif</a></td><td align="right">10-Feb-2017 11:28  </td><td align="right">395K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101638G13I02.tif">1702101638G13I02.tif</a></td><td align="right">10-Feb-2017 11:47  </td><td align="right">399K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101705G13I02.tif">1702101705G13I02.tif</a></td><td align="right">10-Feb-2017 12:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101735G13I02.tif">1702101735G13I02.tif</a></td><td align="right">10-Feb-2017 12:39  </td><td align="right">324K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101745G13I02.tif">1702101745G13I02.tif</a></td><td align="right">10-Feb-2017 13:20  </td><td align="right">437K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101835G13I02.tif">1702101835G13I02.tif</a></td><td align="right">10-Feb-2017 13:39  </td><td align="right">319K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101905G13I02.tif">1702101905G13I02.tif</a></td><td align="right">10-Feb-2017 14:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702101935G13I02.tif">1702101935G13I02.tif</a></td><td align="right">10-Feb-2017 14:39  </td><td align="right">307K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102005G13I02.tif">1702102005G13I02.tif</a></td><td align="right">10-Feb-2017 15:08  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102035G13I02.tif">1702102035G13I02.tif</a></td><td align="right">10-Feb-2017 15:39  </td><td align="right">297K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102045G13I02.tif">1702102045G13I02.tif</a></td><td align="right">10-Feb-2017 16:19  </td><td align="right">390K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102135G13I02.tif">1702102135G13I02.tif</a></td><td align="right">10-Feb-2017 16:39  </td><td align="right">288K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102205G13I02.tif">1702102205G13I02.tif</a></td><td align="right">10-Feb-2017 17:08  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102235G13I02.tif">1702102235G13I02.tif</a></td><td align="right">10-Feb-2017 17:39  </td><td align="right">280K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102305G13I02.tif">1702102305G13I02.tif</a></td><td align="right">10-Feb-2017 18:09  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102335G13I02.tif">1702102335G13I02.tif</a></td><td align="right">10-Feb-2017 18:39  </td><td align="right">275K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702102345G13I02.tif">1702102345G13I02.tif</a></td><td align="right">10-Feb-2017 19:21  </td><td align="right">378K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110105G13I02.tif">1702110105G13I02.tif</a></td><td align="right">10-Feb-2017 20:08  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110135G13I02.tif">1702110135G13I02.tif</a></td><td align="right">10-Feb-2017 20:39  </td><td align="right">278K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110205G13I02.tif">1702110205G13I02.tif</a></td><td align="right">10-Feb-2017 21:09  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110235G13I02.tif">1702110235G13I02.tif</a></td><td align="right">10-Feb-2017 21:39  </td><td align="right">278K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110245G13I02.tif">1702110245G13I02.tif</a></td><td align="right">10-Feb-2017 22:21  </td><td align="right">381K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110335G13I02.tif">1702110335G13I02.tif</a></td><td align="right">10-Feb-2017 22:39  </td><td align="right">275K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110405G13I02.tif">1702110405G13I02.tif</a></td><td align="right">10-Feb-2017 23:09  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110435G13I02.tif">1702110435G13I02.tif</a></td><td align="right">10-Feb-2017 23:39  </td><td align="right">275K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110505G13I02.tif">1702110505G13I02.tif</a></td><td align="right">11-Feb-2017 00:09  </td><td align="right"> 14K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110535G13I02.tif">1702110535G13I02.tif</a></td><td align="right">11-Feb-2017 00:39  </td><td align="right">278K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110545G13I02.tif">1702110545G13I02.tif</a></td><td align="right">11-Feb-2017 01:21  </td><td align="right">390K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110635G13I02.tif">1702110635G13I02.tif</a></td><td align="right">11-Feb-2017 01:39  </td><td align="right">280K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110705G13I02.tif">1702110705G13I02.tif</a></td><td align="right">11-Feb-2017 02:09  </td><td align="right"> 15K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110735G13I02.tif">1702110735G13I02.tif</a></td><td align="right">11-Feb-2017 02:39  </td><td align="right">286K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110805G13I02.tif">1702110805G13I02.tif</a></td><td align="right">11-Feb-2017 03:08  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110835G13I02.tif">1702110835G13I02.tif</a></td><td align="right">11-Feb-2017 03:39  </td><td align="right">286K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110845G13I02.tif">1702110845G13I02.tif</a></td><td align="right">11-Feb-2017 04:20  </td><td align="right">404K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702110935G13I02.tif">1702110935G13I02.tif</a></td><td align="right">11-Feb-2017 04:39  </td><td align="right">285K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111005G13I02.tif">1702111005G13I02.tif</a></td><td align="right">11-Feb-2017 05:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111035G13I02.tif">1702111035G13I02.tif</a></td><td align="right">11-Feb-2017 05:39  </td><td align="right">280K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111105G13I02.tif">1702111105G13I02.tif</a></td><td align="right">11-Feb-2017 06:08  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111135G13I02.tif">1702111135G13I02.tif</a></td><td align="right">11-Feb-2017 06:39  </td><td align="right">279K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111153G13I02.tif">1702111153G13I02.tif</a></td><td align="right">11-Feb-2017 07:20  </td><td align="right">386K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111235G13I02.tif">1702111235G13I02.tif</a></td><td align="right">11-Feb-2017 07:39  </td><td align="right">288K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111305G13I02.tif">1702111305G13I02.tif</a></td><td align="right">11-Feb-2017 08:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111335G13I02.tif">1702111335G13I02.tif</a></td><td align="right">11-Feb-2017 08:39  </td><td align="right">303K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111405G13I02.tif">1702111405G13I02.tif</a></td><td align="right">11-Feb-2017 09:08  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111435G13I02.tif">1702111435G13I02.tif</a></td><td align="right">11-Feb-2017 09:38  </td><td align="right">317K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111445G13I02.tif">1702111445G13I02.tif</a></td><td align="right">11-Feb-2017 10:20  </td><td align="right">436K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111605G13I02.tif">1702111605G13I02.tif</a></td><td align="right">11-Feb-2017 11:08  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111635G13I02.tif">1702111635G13I02.tif</a></td><td align="right">11-Feb-2017 11:38  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111705G13I02.tif">1702111705G13I02.tif</a></td><td align="right">11-Feb-2017 12:09  </td><td align="right"> 19K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111735G13I02.tif">1702111735G13I02.tif</a></td><td align="right">11-Feb-2017 12:39  </td><td align="right">337K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111745G13I02.tif">1702111745G13I02.tif</a></td><td align="right">11-Feb-2017 13:17  </td><td align="right">455K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111835G13I02.tif">1702111835G13I02.tif</a></td><td align="right">11-Feb-2017 13:39  </td><td align="right">331K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111905G13I02.tif">1702111905G13I02.tif</a></td><td align="right">11-Feb-2017 14:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702111935G13I02.tif">1702111935G13I02.tif</a></td><td align="right">11-Feb-2017 14:39  </td><td align="right">322K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112005G13I02.tif">1702112005G13I02.tif</a></td><td align="right">11-Feb-2017 15:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112035G13I02.tif">1702112035G13I02.tif</a></td><td align="right">11-Feb-2017 15:39  </td><td align="right">310K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112045G13I02.tif">1702112045G13I02.tif</a></td><td align="right">11-Feb-2017 16:19  </td><td align="right">407K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112135G13I02.tif">1702112135G13I02.tif</a></td><td align="right">11-Feb-2017 16:39  </td><td align="right">298K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112205G13I02.tif">1702112205G13I02.tif</a></td><td align="right">11-Feb-2017 17:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112235G13I02.tif">1702112235G13I02.tif</a></td><td align="right">11-Feb-2017 17:39  </td><td align="right">287K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112305G13I02.tif">1702112305G13I02.tif</a></td><td align="right">11-Feb-2017 18:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112335G13I02.tif">1702112335G13I02.tif</a></td><td align="right">11-Feb-2017 18:39  </td><td align="right">283K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702112345G13I02.tif">1702112345G13I02.tif</a></td><td align="right">11-Feb-2017 19:21  </td><td align="right">389K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120105G13I02.tif">1702120105G13I02.tif</a></td><td align="right">11-Feb-2017 20:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120135G13I02.tif">1702120135G13I02.tif</a></td><td align="right">11-Feb-2017 20:38  </td><td align="right">279K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120205G13I02.tif">1702120205G13I02.tif</a></td><td align="right">11-Feb-2017 21:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120235G13I02.tif">1702120235G13I02.tif</a></td><td align="right">11-Feb-2017 21:39  </td><td align="right">277K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120245G13I02.tif">1702120245G13I02.tif</a></td><td align="right">11-Feb-2017 22:21  </td><td align="right">389K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120335G13I02.tif">1702120335G13I02.tif</a></td><td align="right">11-Feb-2017 22:39  </td><td align="right">274K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120405G13I02.tif">1702120405G13I02.tif</a></td><td align="right">11-Feb-2017 23:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120435G13I02.tif">1702120435G13I02.tif</a></td><td align="right">11-Feb-2017 23:39  </td><td align="right">274K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120505G13I02.tif">1702120505G13I02.tif</a></td><td align="right">12-Feb-2017 00:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120535G13I02.tif">1702120535G13I02.tif</a></td><td align="right">12-Feb-2017 00:39  </td><td align="right">273K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120545G13I02.tif">1702120545G13I02.tif</a></td><td align="right">12-Feb-2017 01:20  </td><td align="right">393K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120638G13I02.tif">1702120638G13I02.tif</a></td><td align="right">12-Feb-2017 01:48  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120708G13I02.tif">1702120708G13I02.tif</a></td><td align="right">12-Feb-2017 02:17  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120738G13I02.tif">1702120738G13I02.tif</a></td><td align="right">12-Feb-2017 02:47  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120808G13I02.tif">1702120808G13I02.tif</a></td><td align="right">12-Feb-2017 03:17  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120838G13I02.tif">1702120838G13I02.tif</a></td><td align="right">12-Feb-2017 03:47  </td><td align="right">359K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120845G13I02.tif">1702120845G13I02.tif</a></td><td align="right">12-Feb-2017 04:21  </td><td align="right">399K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702120938G13I02.tif">1702120938G13I02.tif</a></td><td align="right">12-Feb-2017 04:47  </td><td align="right">359K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121008G13I02.tif">1702121008G13I02.tif</a></td><td align="right">12-Feb-2017 05:17  </td><td align="right">353K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121038G13I02.tif">1702121038G13I02.tif</a></td><td align="right">12-Feb-2017 05:47  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121108G13I02.tif">1702121108G13I02.tif</a></td><td align="right">12-Feb-2017 06:17  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121138G13I02.tif">1702121138G13I02.tif</a></td><td align="right">12-Feb-2017 06:46  </td><td align="right">337K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121145G13I02.tif">1702121145G13I02.tif</a></td><td align="right">12-Feb-2017 07:20  </td><td align="right">369K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121238G13I02.tif">1702121238G13I02.tif</a></td><td align="right">12-Feb-2017 07:47  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121308G13I02.tif">1702121308G13I02.tif</a></td><td align="right">12-Feb-2017 08:17  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121338G13I02.tif">1702121338G13I02.tif</a></td><td align="right">12-Feb-2017 08:47  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121408G13I02.tif">1702121408G13I02.tif</a></td><td align="right">12-Feb-2017 09:17  </td><td align="right">371K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121438G13I02.tif">1702121438G13I02.tif</a></td><td align="right">12-Feb-2017 09:46  </td><td align="right">380K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121445G13I02.tif">1702121445G13I02.tif</a></td><td align="right">12-Feb-2017 10:20  </td><td align="right">425K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121608G13I02.tif">1702121608G13I02.tif</a></td><td align="right">12-Feb-2017 11:17  </td><td align="right">402K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121638G13I02.tif">1702121638G13I02.tif</a></td><td align="right">12-Feb-2017 11:47  </td><td align="right">408K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121708G13I02.tif">1702121708G13I02.tif</a></td><td align="right">12-Feb-2017 12:15  </td><td align="right">412K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121738G13I02.tif">1702121738G13I02.tif</a></td><td align="right">12-Feb-2017 12:46  </td><td align="right">411K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121745G13I02.tif">1702121745G13I02.tif</a></td><td align="right">12-Feb-2017 13:20  </td><td align="right">448K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121838G13I02.tif">1702121838G13I02.tif</a></td><td align="right">12-Feb-2017 13:45  </td><td align="right">403K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121908G13I02.tif">1702121908G13I02.tif</a></td><td align="right">12-Feb-2017 14:17  </td><td align="right">396K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702121938G13I02.tif">1702121938G13I02.tif</a></td><td align="right">12-Feb-2017 14:46  </td><td align="right">388K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122008G13I02.tif">1702122008G13I02.tif</a></td><td align="right">12-Feb-2017 15:17  </td><td align="right">380K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122038G13I02.tif">1702122038G13I02.tif</a></td><td align="right">12-Feb-2017 15:47  </td><td align="right">372K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122045G13I02.tif">1702122045G13I02.tif</a></td><td align="right">12-Feb-2017 16:20  </td><td align="right">398K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122138G13I02.tif">1702122138G13I02.tif</a></td><td align="right">12-Feb-2017 16:47  </td><td align="right">355K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122208G13I02.tif">1702122208G13I02.tif</a></td><td align="right">12-Feb-2017 17:17  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122238G13I02.tif">1702122238G13I02.tif</a></td><td align="right">12-Feb-2017 17:47  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122308G13I02.tif">1702122308G13I02.tif</a></td><td align="right">12-Feb-2017 18:17  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122338G13I02.tif">1702122338G13I02.tif</a></td><td align="right">12-Feb-2017 18:46  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702122345G13I02.tif">1702122345G13I02.tif</a></td><td align="right">12-Feb-2017 19:21  </td><td align="right">380K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130038G13I02.tif">1702130038G13I02.tif</a></td><td align="right">12-Feb-2017 19:47  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130108G13I02.tif">1702130108G13I02.tif</a></td><td align="right">12-Feb-2017 20:17  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130138G13I02.tif">1702130138G13I02.tif</a></td><td align="right">12-Feb-2017 20:47  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130208G13I02.tif">1702130208G13I02.tif</a></td><td align="right">12-Feb-2017 21:17  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130238G13I02.tif">1702130238G13I02.tif</a></td><td align="right">12-Feb-2017 21:47  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130245G13I02.tif">1702130245G13I02.tif</a></td><td align="right">12-Feb-2017 22:21  </td><td align="right">375K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130338G13I02.tif">1702130338G13I02.tif</a></td><td align="right">12-Feb-2017 22:47  </td><td align="right">338K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130408G13I02.tif">1702130408G13I02.tif</a></td><td align="right">12-Feb-2017 23:18  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130438G13I02.tif">1702130438G13I02.tif</a></td><td align="right">12-Feb-2017 23:48  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130508G13I02.tif">1702130508G13I02.tif</a></td><td align="right">13-Feb-2017 00:16  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130538G13I02.tif">1702130538G13I02.tif</a></td><td align="right">13-Feb-2017 00:47  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130545G13I02.tif">1702130545G13I02.tif</a></td><td align="right">13-Feb-2017 01:21  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130638G13I02.tif">1702130638G13I02.tif</a></td><td align="right">13-Feb-2017 01:47  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130708G13I02.tif">1702130708G13I02.tif</a></td><td align="right">13-Feb-2017 02:17  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130738G13I02.tif">1702130738G13I02.tif</a></td><td align="right">13-Feb-2017 02:48  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130808G13I02.tif">1702130808G13I02.tif</a></td><td align="right">13-Feb-2017 03:17  </td><td align="right">349K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130838G13I02.tif">1702130838G13I02.tif</a></td><td align="right">13-Feb-2017 03:46  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130845G13I02.tif">1702130845G13I02.tif</a></td><td align="right">13-Feb-2017 04:20  </td><td align="right">392K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702130938G13I02.tif">1702130938G13I02.tif</a></td><td align="right">13-Feb-2017 04:48  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131008G13I02.tif">1702131008G13I02.tif</a></td><td align="right">13-Feb-2017 05:17  </td><td align="right">342K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131038G13I02.tif">1702131038G13I02.tif</a></td><td align="right">13-Feb-2017 05:47  </td><td align="right">337K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131108G13I02.tif">1702131108G13I02.tif</a></td><td align="right">13-Feb-2017 06:17  </td><td align="right">330K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131138G13I02.tif">1702131138G13I02.tif</a></td><td align="right">13-Feb-2017 06:46  </td><td align="right">325K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131145G13I02.tif">1702131145G13I02.tif</a></td><td align="right">13-Feb-2017 07:20  </td><td align="right">361K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131238G13I02.tif">1702131238G13I02.tif</a></td><td align="right">13-Feb-2017 07:47  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131308G13I02.tif">1702131308G13I02.tif</a></td><td align="right">13-Feb-2017 08:17  </td><td align="right">349K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131338G13I02.tif">1702131338G13I02.tif</a></td><td align="right">13-Feb-2017 08:47  </td><td align="right">363K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131408G13I02.tif">1702131408G13I02.tif</a></td><td align="right">13-Feb-2017 09:17  </td><td align="right">375K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131438G13I02.tif">1702131438G13I02.tif</a></td><td align="right">13-Feb-2017 09:47  </td><td align="right">384K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131445G13I02.tif">1702131445G13I02.tif</a></td><td align="right">13-Feb-2017 10:19  </td><td align="right">426K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131608G13I02.tif">1702131608G13I02.tif</a></td><td align="right">13-Feb-2017 11:17  </td><td align="right">403K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131638G13I02.tif">1702131638G13I02.tif</a></td><td align="right">13-Feb-2017 11:46  </td><td align="right">410K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131708G13I02.tif">1702131708G13I02.tif</a></td><td align="right">13-Feb-2017 12:15  </td><td align="right">413K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131738G13I02.tif">1702131738G13I02.tif</a></td><td align="right">13-Feb-2017 12:46  </td><td align="right">411K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131745G13I02.tif">1702131745G13I02.tif</a></td><td align="right">13-Feb-2017 13:20  </td><td align="right">441K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131838G13I02.tif">1702131838G13I02.tif</a></td><td align="right">13-Feb-2017 13:47  </td><td align="right">401K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131908G13I02.tif">1702131908G13I02.tif</a></td><td align="right">13-Feb-2017 14:17  </td><td align="right">396K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702131938G13I02.tif">1702131938G13I02.tif</a></td><td align="right">13-Feb-2017 14:45  </td><td align="right">388K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132008G13I02.tif">1702132008G13I02.tif</a></td><td align="right">13-Feb-2017 15:14  </td><td align="right">379K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132038G13I02.tif">1702132038G13I02.tif</a></td><td align="right">13-Feb-2017 15:46  </td><td align="right">369K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132045G13I02.tif">1702132045G13I02.tif</a></td><td align="right">13-Feb-2017 16:20  </td><td align="right">390K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132138G13I02.tif">1702132138G13I02.tif</a></td><td align="right">13-Feb-2017 16:47  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132208G13I02.tif">1702132208G13I02.tif</a></td><td align="right">13-Feb-2017 17:16  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132238G13I02.tif">1702132238G13I02.tif</a></td><td align="right">13-Feb-2017 17:47  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132308G13I02.tif">1702132308G13I02.tif</a></td><td align="right">13-Feb-2017 18:17  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132338G13I02.tif">1702132338G13I02.tif</a></td><td align="right">13-Feb-2017 18:46  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702132345G13I02.tif">1702132345G13I02.tif</a></td><td align="right">13-Feb-2017 19:21  </td><td align="right">368K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140038G13I02.tif">1702140038G13I02.tif</a></td><td align="right">13-Feb-2017 19:47  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140108G13I02.tif">1702140108G13I02.tif</a></td><td align="right">13-Feb-2017 20:17  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140138G13I02.tif">1702140138G13I02.tif</a></td><td align="right">13-Feb-2017 20:47  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140208G13I02.tif">1702140208G13I02.tif</a></td><td align="right">13-Feb-2017 21:17  </td><td align="right">339K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140238G13I02.tif">1702140238G13I02.tif</a></td><td align="right">13-Feb-2017 21:47  </td><td align="right">338K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140245G13I02.tif">1702140245G13I02.tif</a></td><td align="right">13-Feb-2017 22:21  </td><td align="right">364K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140338G13I02.tif">1702140338G13I02.tif</a></td><td align="right">13-Feb-2017 22:47  </td><td align="right">332K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140408G13I02.tif">1702140408G13I02.tif</a></td><td align="right">13-Feb-2017 23:17  </td><td align="right">330K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140438G13I02.tif">1702140438G13I02.tif</a></td><td align="right">13-Feb-2017 23:47  </td><td align="right">327K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140508G13I02.tif">1702140508G13I02.tif</a></td><td align="right">14-Feb-2017 00:15  </td><td align="right">330K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140538G13I02.tif">1702140538G13I02.tif</a></td><td align="right">14-Feb-2017 00:46  </td><td align="right">328K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140545G13I02.tif">1702140545G13I02.tif</a></td><td align="right">14-Feb-2017 01:22  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140638G13I02.tif">1702140638G13I02.tif</a></td><td align="right">14-Feb-2017 01:48  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140708G13I02.tif">1702140708G13I02.tif</a></td><td align="right">14-Feb-2017 02:17  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140738G13I02.tif">1702140738G13I02.tif</a></td><td align="right">14-Feb-2017 02:47  </td><td align="right">344K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140808G13I02.tif">1702140808G13I02.tif</a></td><td align="right">14-Feb-2017 03:18  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140838G13I02.tif">1702140838G13I02.tif</a></td><td align="right">14-Feb-2017 03:46  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140845G13I02.tif">1702140845G13I02.tif</a></td><td align="right">14-Feb-2017 04:20  </td><td align="right">380K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702140938G13I02.tif">1702140938G13I02.tif</a></td><td align="right">14-Feb-2017 04:47  </td><td align="right">343K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141008G13I02.tif">1702141008G13I02.tif</a></td><td align="right">14-Feb-2017 05:17  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141038G13I02.tif">1702141038G13I02.tif</a></td><td align="right">14-Feb-2017 05:47  </td><td align="right">338K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141108G13I02.tif">1702141108G13I02.tif</a></td><td align="right">14-Feb-2017 06:18  </td><td align="right">332K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141138G13I02.tif">1702141138G13I02.tif</a></td><td align="right">14-Feb-2017 06:46  </td><td align="right">329K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141145G13I02.tif">1702141145G13I02.tif</a></td><td align="right">14-Feb-2017 07:20  </td><td align="right">364K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141235G13I02.tif">1702141235G13I02.tif</a></td><td align="right">14-Feb-2017 07:39  </td><td align="right">274K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141305G13I02.tif">1702141305G13I02.tif</a></td><td align="right">14-Feb-2017 08:09  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141335G13I02.tif">1702141335G13I02.tif</a></td><td align="right">14-Feb-2017 08:38  </td><td align="right">297K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141405G13I02.tif">1702141405G13I02.tif</a></td><td align="right">14-Feb-2017 09:09  </td><td align="right"> 17K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141435G13I02.tif">1702141435G13I02.tif</a></td><td align="right">14-Feb-2017 09:39  </td><td align="right">314K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141445G13I02.tif">1702141445G13I02.tif</a></td><td align="right">14-Feb-2017 10:20  </td><td align="right">432K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141605G13I02.tif">1702141605G13I02.tif</a></td><td align="right">14-Feb-2017 11:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141635G13I02.tif">1702141635G13I02.tif</a></td><td align="right">14-Feb-2017 11:39  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141705G13I02.tif">1702141705G13I02.tif</a></td><td align="right">14-Feb-2017 12:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141735G13I02.tif">1702141735G13I02.tif</a></td><td align="right">14-Feb-2017 12:39  </td><td align="right">337K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141745G13I02.tif">1702141745G13I02.tif</a></td><td align="right">14-Feb-2017 13:20  </td><td align="right">454K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141835G13I02.tif">1702141835G13I02.tif</a></td><td align="right">14-Feb-2017 13:39  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141905G13I02.tif">1702141905G13I02.tif</a></td><td align="right">14-Feb-2017 14:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702141935G13I02.tif">1702141935G13I02.tif</a></td><td align="right">14-Feb-2017 14:39  </td><td align="right">325K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142005G13I02.tif">1702142005G13I02.tif</a></td><td align="right">14-Feb-2017 15:09  </td><td align="right"> 18K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142035G13I02.tif">1702142035G13I02.tif</a></td><td align="right">14-Feb-2017 15:39  </td><td align="right">314K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142045G13I02.tif">1702142045G13I02.tif</a></td><td align="right">14-Feb-2017 16:19  </td><td align="right">408K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142135G13I02.tif">1702142135G13I02.tif</a></td><td align="right">14-Feb-2017 16:39  </td><td align="right">298K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142205G13I02.tif">1702142205G13I02.tif</a></td><td align="right">14-Feb-2017 17:09  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142235G13I02.tif">1702142235G13I02.tif</a></td><td align="right">14-Feb-2017 17:38  </td><td align="right">289K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142305G13I02.tif">1702142305G13I02.tif</a></td><td align="right">14-Feb-2017 18:09  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142335G13I02.tif">1702142335G13I02.tif</a></td><td align="right">14-Feb-2017 18:39  </td><td align="right">287K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702142345G13I02.tif">1702142345G13I02.tif</a></td><td align="right">14-Feb-2017 19:21  </td><td align="right">388K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150105G13I02.tif">1702150105G13I02.tif</a></td><td align="right">14-Feb-2017 20:09  </td><td align="right"> 16K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150138G13I02.tif">1702150138G13I02.tif</a></td><td align="right">14-Feb-2017 20:47  </td><td align="right">358K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150208G13I02.tif">1702150208G13I02.tif</a></td><td align="right">14-Feb-2017 21:18  </td><td align="right">358K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150238G13I02.tif">1702150238G13I02.tif</a></td><td align="right">14-Feb-2017 21:46  </td><td align="right">357K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150245G13I02.tif">1702150245G13I02.tif</a></td><td align="right">14-Feb-2017 22:21  </td><td align="right">384K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150338G13I02.tif">1702150338G13I02.tif</a></td><td align="right">14-Feb-2017 22:47  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150408G13I02.tif">1702150408G13I02.tif</a></td><td align="right">14-Feb-2017 23:17  </td><td align="right">349K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150438G13I02.tif">1702150438G13I02.tif</a></td><td align="right">14-Feb-2017 23:47  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150508G13I02.tif">1702150508G13I02.tif</a></td><td align="right">15-Feb-2017 00:16  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150538G13I02.tif">1702150538G13I02.tif</a></td><td align="right">15-Feb-2017 00:46  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150545G13I02.tif">1702150545G13I02.tif</a></td><td align="right">15-Feb-2017 01:21  </td><td align="right">381K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150638G13I02.tif">1702150638G13I02.tif</a></td><td align="right">15-Feb-2017 01:47  </td><td align="right">355K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150708G13I02.tif">1702150708G13I02.tif</a></td><td align="right">15-Feb-2017 02:18  </td><td align="right">357K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150738G13I02.tif">1702150738G13I02.tif</a></td><td align="right">15-Feb-2017 02:47  </td><td align="right">359K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150808G13I02.tif">1702150808G13I02.tif</a></td><td align="right">15-Feb-2017 03:17  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150838G13I02.tif">1702150838G13I02.tif</a></td><td align="right">15-Feb-2017 03:46  </td><td align="right">359K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150845G13I02.tif">1702150845G13I02.tif</a></td><td align="right">15-Feb-2017 04:21  </td><td align="right">387K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702150938G13I02.tif">1702150938G13I02.tif</a></td><td align="right">15-Feb-2017 04:48  </td><td align="right">350K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151008G13I02.tif">1702151008G13I02.tif</a></td><td align="right">15-Feb-2017 05:17  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151038G13I02.tif">1702151038G13I02.tif</a></td><td align="right">15-Feb-2017 05:47  </td><td align="right">337K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151108G13I02.tif">1702151108G13I02.tif</a></td><td align="right">15-Feb-2017 06:17  </td><td align="right">327K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151138G13I02.tif">1702151138G13I02.tif</a></td><td align="right">15-Feb-2017 06:46  </td><td align="right">323K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151338G13I02.tif">1702151338G13I02.tif</a></td><td align="right">15-Feb-2017 09:40  </td><td align="right">355K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151408G13I02.tif">1702151408G13I02.tif</a></td><td align="right">15-Feb-2017 09:54  </td><td align="right">364K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151438G13I02.tif">1702151438G13I02.tif</a></td><td align="right">15-Feb-2017 09:56  </td><td align="right">372K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151445G13I02.tif">1702151445G13I02.tif</a></td><td align="right">15-Feb-2017 10:20  </td><td align="right">414K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151608G13I02.tif">1702151608G13I02.tif</a></td><td align="right">15-Feb-2017 11:17  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151638G13I02.tif">1702151638G13I02.tif</a></td><td align="right">15-Feb-2017 11:47  </td><td align="right">398K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151738G13I02.tif">1702151738G13I02.tif</a></td><td align="right">15-Feb-2017 12:46  </td><td align="right">403K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151745G13I02.tif">1702151745G13I02.tif</a></td><td align="right">15-Feb-2017 13:19  </td><td align="right">437K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151838G13I02.tif">1702151838G13I02.tif</a></td><td align="right">15-Feb-2017 13:46  </td><td align="right">396K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151908G13I02.tif">1702151908G13I02.tif</a></td><td align="right">15-Feb-2017 14:17  </td><td align="right">389K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702151938G13I02.tif">1702151938G13I02.tif</a></td><td align="right">15-Feb-2017 14:47  </td><td align="right">381K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152008G13I02.tif">1702152008G13I02.tif</a></td><td align="right">15-Feb-2017 15:17  </td><td align="right">372K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152038G13I02.tif">1702152038G13I02.tif</a></td><td align="right">15-Feb-2017 15:46  </td><td align="right">362K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152045G13I02.tif">1702152045G13I02.tif</a></td><td align="right">15-Feb-2017 16:20  </td><td align="right">388K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152138G13I02.tif">1702152138G13I02.tif</a></td><td align="right">15-Feb-2017 16:47  </td><td align="right">347K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152208G13I02.tif">1702152208G13I02.tif</a></td><td align="right">15-Feb-2017 17:15  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152238G13I02.tif">1702152238G13I02.tif</a></td><td align="right">15-Feb-2017 17:47  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152308G13I02.tif">1702152308G13I02.tif</a></td><td align="right">15-Feb-2017 18:17  </td><td align="right">332K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152338G13I02.tif">1702152338G13I02.tif</a></td><td align="right">15-Feb-2017 18:46  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702152345G13I02.tif">1702152345G13I02.tif</a></td><td align="right">15-Feb-2017 19:21  </td><td align="right">366K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160038G13I02.tif">1702160038G13I02.tif</a></td><td align="right">15-Feb-2017 19:47  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160108G13I02.tif">1702160108G13I02.tif</a></td><td align="right">15-Feb-2017 20:17  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160138G13I02.tif">1702160138G13I02.tif</a></td><td align="right">15-Feb-2017 20:47  </td><td align="right">338K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160208G13I02.tif">1702160208G13I02.tif</a></td><td align="right">15-Feb-2017 21:17  </td><td align="right">338K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160238G13I02.tif">1702160238G13I02.tif</a></td><td align="right">15-Feb-2017 21:46  </td><td align="right">339K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160245G13I02.tif">1702160245G13I02.tif</a></td><td align="right">15-Feb-2017 22:21  </td><td align="right">368K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160338G13I02.tif">1702160338G13I02.tif</a></td><td align="right">15-Feb-2017 22:47  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160408G13I02.tif">1702160408G13I02.tif</a></td><td align="right">15-Feb-2017 23:17  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160438G13I02.tif">1702160438G13I02.tif</a></td><td align="right">15-Feb-2017 23:47  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160508G13I02.tif">1702160508G13I02.tif</a></td><td align="right">16-Feb-2017 00:15  </td><td align="right">335K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160538G13I02.tif">1702160538G13I02.tif</a></td><td align="right">16-Feb-2017 00:46  </td><td align="right">335K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160545G13I02.tif">1702160545G13I02.tif</a></td><td align="right">16-Feb-2017 01:21  </td><td align="right">374K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160638G13I02.tif">1702160638G13I02.tif</a></td><td align="right">16-Feb-2017 01:48  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160708G13I02.tif">1702160708G13I02.tif</a></td><td align="right">16-Feb-2017 02:18  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160738G13I02.tif">1702160738G13I02.tif</a></td><td align="right">16-Feb-2017 02:47  </td><td align="right">349K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160808G13I02.tif">1702160808G13I02.tif</a></td><td align="right">16-Feb-2017 03:18  </td><td align="right">350K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160838G13I02.tif">1702160838G13I02.tif</a></td><td align="right">16-Feb-2017 03:46  </td><td align="right">348K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160845G13I02.tif">1702160845G13I02.tif</a></td><td align="right">16-Feb-2017 04:20  </td><td align="right">384K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702160938G13I02.tif">1702160938G13I02.tif</a></td><td align="right">16-Feb-2017 04:48  </td><td align="right">345K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161008G13I02.tif">1702161008G13I02.tif</a></td><td align="right">16-Feb-2017 05:17  </td><td align="right">341K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161038G13I02.tif">1702161038G13I02.tif</a></td><td align="right">16-Feb-2017 05:47  </td><td align="right">334K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161108G13I02.tif">1702161108G13I02.tif</a></td><td align="right">16-Feb-2017 06:17  </td><td align="right">326K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161138G13I02.tif">1702161138G13I02.tif</a></td><td align="right">16-Feb-2017 06:47  </td><td align="right">321K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161156G13I02.tif">1702161156G13I02.tif</a></td><td align="right">16-Feb-2017 07:20  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161238G13I02.tif">1702161238G13I02.tif</a></td><td align="right">16-Feb-2017 07:47  </td><td align="right">333K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161308G13I02.tif">1702161308G13I02.tif</a></td><td align="right">16-Feb-2017 08:17  </td><td align="right">342K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161338G13I02.tif">1702161338G13I02.tif</a></td><td align="right">16-Feb-2017 08:47  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161408G13I02.tif">1702161408G13I02.tif</a></td><td align="right">16-Feb-2017 09:17  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161438G13I02.tif">1702161438G13I02.tif</a></td><td align="right">16-Feb-2017 09:46  </td><td align="right">368K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161445G13I02.tif">1702161445G13I02.tif</a></td><td align="right">16-Feb-2017 10:19  </td><td align="right">415K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161608G13I02.tif">1702161608G13I02.tif</a></td><td align="right">16-Feb-2017 11:16  </td><td align="right">388K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161638G13I02.tif">1702161638G13I02.tif</a></td><td align="right">16-Feb-2017 11:47  </td><td align="right">392K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161708G13I02.tif">1702161708G13I02.tif</a></td><td align="right">16-Feb-2017 12:15  </td><td align="right">393K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161738G13I02.tif">1702161738G13I02.tif</a></td><td align="right">16-Feb-2017 12:47  </td><td align="right">391K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161745G13I02.tif">1702161745G13I02.tif</a></td><td align="right">16-Feb-2017 13:20  </td><td align="right">428K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161838G13I02.tif">1702161838G13I02.tif</a></td><td align="right">16-Feb-2017 13:47  </td><td align="right">381K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161908G13I02.tif">1702161908G13I02.tif</a></td><td align="right">16-Feb-2017 14:17  </td><td align="right">376K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702161938G13I02.tif">1702161938G13I02.tif</a></td><td align="right">16-Feb-2017 14:47  </td><td align="right">369K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162008G13I02.tif">1702162008G13I02.tif</a></td><td align="right">16-Feb-2017 15:17  </td><td align="right">360K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162038G13I02.tif">1702162038G13I02.tif</a></td><td align="right">16-Feb-2017 15:46  </td><td align="right">352K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162045G13I02.tif">1702162045G13I02.tif</a></td><td align="right">16-Feb-2017 16:20  </td><td align="right">383K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162138G13I02.tif">1702162138G13I02.tif</a></td><td align="right">16-Feb-2017 16:45  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162208G13I02.tif">1702162208G13I02.tif</a></td><td align="right">16-Feb-2017 17:17  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162238G13I02.tif">1702162238G13I02.tif</a></td><td align="right">16-Feb-2017 17:45  </td><td align="right">336K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162308G13I02.tif">1702162308G13I02.tif</a></td><td align="right">16-Feb-2017 18:17  </td><td align="right">340K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162338G13I02.tif">1702162338G13I02.tif</a></td><td align="right">16-Feb-2017 18:46  </td><td align="right">346K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702162345G13I02.tif">1702162345G13I02.tif</a></td><td align="right">16-Feb-2017 19:20  </td><td align="right">384K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702170038G13I02.tif">1702170038G13I02.tif</a></td><td align="right">16-Feb-2017 19:47  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702170108G13I02.tif">1702170108G13I02.tif</a></td><td align="right">16-Feb-2017 20:17  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702170138G13I02.tif">1702170138G13I02.tif</a></td><td align="right">16-Feb-2017 20:47  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702170208G13I02.tif">1702170208G13I02.tif</a></td><td align="right">16-Feb-2017 21:17  </td><td align="right">351K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="1702170238G13I02.tif">1702170238G13I02.tif</a></td><td align="right">16-Feb-2017 21:47  </td><td align="right">350K</td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/unknown.gif" alt="[   ]"></td><td><a href="latest.info">latest.info</a></td><td align="right">16-Feb-2017 21:47  </td><td align="right">530 </td><td>&nbsp;</td></tr>
<tr><td valign="top"><img src="/icons/image2.gif" alt="[IMG]"></td><td><a href="latest.tif">latest.tif</a></td><td align="right">16-Feb-2017 21:47  </td><td align="right">350K</td><td>&nbsp;</td></tr>
<tr><th colspan="5"><hr></th></tr>
</table>
</body></html>
                
EOT;


        // produtcion:
//        $guzzleClient = new Client(array('base_uri'=>GOES_BASE));
//        $response = $guzzleClient->get(GOES_FOLDER);
//        $html = $response->getBody()->getContents();



        return $html;
    }

}
