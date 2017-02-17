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

class DownloadCommand extends ContainerAwareCommand {

    private $dm;
    
    /* @var $client S3Client */
    private $client;
    
    protected function configure() {
        $this
                ->setName('goesir:daily:download')
                ->setDescription('');
                //->addArgument('configURL', InputArgument::REQUIRED, 'Especificar URL de archivo de configuración');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $start=date('c');
        
        // Amazon
        $AWSKey = $this->getContainer()->getParameter('amazon_s3.key');
        $AWSSecret = $this->getContainer()->getParameter('amazon_s3.secret');
        $connected=true;
        $failMessage='none';
        try {
            $this->client = S3Client::factory(array('key' => $AWSKey, 'secret' => $AWSSecret));
        }
        catch(\Exception $e) {
            $failMessage=$e->getMessage();
            $connected=false;
        }
        if(!$connected) {
            $output->writeln('ERROR: imposible conectar a Amazon. '.$failMessage);
            return;
        }

        // Process config
        $configURL=$input->getArgument('configURL');
        $output->writeln('INFO: leyendo configuración de ' . $configURL);
        $configContents = file_get_contents($configURL);
        $output->writeln('INFO: configuración: ' . $configContents);
        $validateJSON = $this->validateJson($configContents);
        if ($validateJSON != 'OK') {
            $output->writeln('ERROR: ' . $validateJSON);
            return;
        }
        $this->config = json_decode($configContents);
        
        
        // user and customer
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        if ($this->dm == null) {
            $output->writeln('ERROR: Unable to connect document manager');
            return;
        }
        $output->writeln('**********************************************************');
        $output->writeln('INFO: Document Manager listo');
        
        
        /* @var $user UserDocument */
        $user = $this->dm->getRepository('AppBundle:UserDocument')->findOneByUsername($this->config->owner);
        if ($user == null) {
            $output->writeln('ERROR: no puedo encontrar al usuario ' . $this->config->owner);
            return;
        }
        $output->writeln('INFO: Usuario: ' . $user->getDisplayName() . ' ' . $user->getId());

        $customer = $user->getCurrentCustomer();
        $output->writeln('INFO: Customer: ' . $customer->getCompany() . ' ' . $customer->getId());

       $localFile = tempnam("/tmp", "sanblas-");
        if ($localFile === false) {
            $output->writeln('ERROR: no puedo crear archivo local temporal.');
            return;
        }

        $localFile.='.xls';

        $output->writeln('INFO: inicio de copia archivo remoto');

        PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
        try {
            $this->client->getObject(array(
                'Bucket' => $this->config->bucket,
                'Key'    => $this->config->baseFolder . '/data.xls',
                'SaveAs' => $localFile
            ));
        }
        catch(\Exception $e) {
            $output->writeln('ERROR: no puedo copiar el archivo remoto al local.');
            return;
        } 

        $output->writeln('INFO: ' . $localFile . ' obtenido de S3. Size: ' . filesize($localFile));

        // get MD5 $parts[1]
        $md5LocalFile = md5_file($localFile);
        if ($md5LocalFile == $this->config->md5) {
            $output->writeln('INFO: archivo remoto MD5 ' . $md5LocalFile . ' OK!');
        } else {
            $output->writeln('ERROR: no coinciden los MD5 del remoto y el local');
            return;
        }

        /* @var $phpExcelObject \PHPExcel */
        $phpExcelObject = $this->getContainer()->get('phpexcel')->createPHPExcelObject($localFile);
        if ($phpExcelObject == null) {
            $output->writeln('ERROR: no puedo abri el archivo excel local.');
            return;
        }

        $sheetData = $phpExcelObject->getActiveSheet()->toArray(' ');

        $linea = 0;
        foreach ($sheetData as $row) {
            $linea++;
            if ($linea == 1) {
                // skip
                continue;
            }

            $i = array();

            $i['rubro'] = $this->sanitize($row[0]); // A
            $i['art'] = $this->sanitize($row[1]); // B
            $i['marca'] = $this->sanitize($row[2]); // C
            $i['modelo'] = $this->sanitize($row[3]); // D
            $i['serie'] = $this->sanitize($row[4]); // E
            $i['cliente'] = $this->sanitize($row[7]); // H

            $inspectables[] = $i;
        }

        $total = 0;

        $objectsAdded=[];
        foreach ($inspectables as $i) {

            if ($i['marca'] == '' || $i['marca'] == ' ' || $i['marca'] == null) {
                continue;
            }
            
            $inspectable = new Inspectable();
            $inspectable->setCreator($user);
            $inspectable->setOwner($customer);
            $inspectable->setName($i['marca'] . ' ' . $i['modelo']);
            $inspectable->setSerialNumber($i['serie']);

            $inspectable->setTags($this->reverseTransform(
                            array($i['rubro'], $i['art'], $i['marca'], $i['modelo'], 'Serie ' . $i['serie'], $i['cliente']), $user, $customer));
            
            $newObject=$inspectableService->create($user, $inspectable);
            
            $total++;
            $output->writeln("INFO: Inspectable {$total} => {$newObject->getId()} => {$newObject->getName()}");
            
            $objectsAdded[]=$newObject->getId();
        }

        

        $output->writeln('INFO: Total Inspectables importados: ' . $total);
        
        $output->writeln('IDs agregados uno por linea:');
        $output->writeln('----------------------------');
        foreach($objectsAdded as $added) {
            $output->writeln($added);
        }
        $output->writeln('----------------------------');
        $output->writeln('Lo mismo pero en JSON:');
        $output->writeln(json_encode($objectsAdded));
        $output->writeln('----------------------------');
        $output->writeln('INFO: Total Inspectables importados: ' . $total);
        
        
        // borramos los anteriores...
        $output->writeln('INFO: debo borrar? '.count($this->config->deletions));
        foreach($this->config->deletions as $borrar) {
            /* @var $i Inspectable */
            $i = $this->dm->getRepository('AppBundle:Inspectable')->find(new \MongoId($borrar));
            if($i==null) {
                $output->writeln('INFO: No encuentro Inspectable '.$borrar);
            }
            else {
                $output->writeln('INFO: Borrando Inspectable '.$i->getId());
                $inspectableService->deleteInspectable($i, $user);
            }
        }
        
        // if everything is ok, add the new inspectables to the deletions objects
        $payload=$this->config;
        $payload->deletions=$objectsAdded;
        $payload->start=$start;
        $payload->userid = $user->getId();
        $payload->customer = $user->getCurrentCustomer()->getId();
        $payload->end=$start=date('c');
        
        $this->client->putObject([
                'Bucket' => $this->config->bucket,
                'Key' => $this->config->baseFolder . '/log.json',
                'Body' => json_encode($payload)
            ]);
        
        $output->writeln('**********************************************************');
        
    }


    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function reverseTransform($data, UserDocument $user, Customer $customer) {

        $tagCollection = new ArrayCollection();

        if ('' === $data || null === $data) {
            return $tagCollection;
        }

        foreach ($data as $name) {
            $tag = $this->dm->createQueryBuilder('AppBundle:Tag')
                    ->field('name')->equals($name)
                    ->field('owner')->references($customer)
                    ->getQuery()
                    ->getSingleResult();
            if (null === $tag) {
                $tag = new Tag();
                $tag->setOwner($customer);
                $tag->setCreator($user);
                $tag->setName($name);
                $this->dm->persist($tag);
                $this->dm->flush();
            }
            $tagCollection->add($tag);
        }
        return $tagCollection;
    }


    
    
    private function sanitize($target) {
        $target = strtoupper($target);
        $target = trim($target);
        $target = str_replace(' ', ' ', $target);
        return $target;
    }


    
    private function validateJson($config) {

        if (!StringHelper::isJson($config)) {
            return 'no es JSON';
        }

        /*
          {
          "bucket": "piritests",
          "baseFolder": "imports/gruas",
          "md5": "e51578254df405ee60760d00fc7afbc6",
          "deletions": [
          "5894a8ca2cb5dd0b2b8b4567",
          "589483182cb5dd3f188b4567",
          "5894a8ca2cb5dd0b2b8b4568"
          ],
          "owner": "eprimucci+gsb@gmail.com"
          }
         */
        $test = json_decode($config);

        if (!isset($test->bucket)) {
            return 'falta el bucket';
        }
        if (!isset($test->baseFolder)) {
            return 'falta el baseFolder';
        }
        if (!isset($test->md5)) {
            return 'falta hash md5 del contenido del archivo data.xls';
        }
        if (!isset($test->deletions)) {
            return 'faltan deletions previas, aunque sea un array vacío';
        }
        if (!isset($test->owner)) {
            return 'falta el owner email';
        }
        if (!StringHelper::is_valid_email_address($test->owner)) {
            return $test->owner . ' no es un email válido';
        }

        return 'OK';
    }

    
    
    
}
