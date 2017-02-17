<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MongoIndexCommand extends ContainerAwareCommand {

    private $dm;


    protected function configure() {
        $this->setName('goesir:mongo:index')
             ->setDescription('Ensure indices on MongoDB collection');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        if ($this->dm == null) {
            $output->writeln('ERROR: Unable to connect document manager');
            return;
        }
        $output->writeln('**********************************************************');
        $output->writeln('INFO: Document Manager listo');

        $this->dm->getSchemaManager()->ensureIndexes();
        
        $output->writeln('INFO: Ensured indices');
        
    }

}
