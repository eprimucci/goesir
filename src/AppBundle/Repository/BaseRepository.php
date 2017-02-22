<?php

namespace AppBundle\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;

class BaseRepository extends DocumentRepository {
    
    private $recsPerPage=20;
    private $biggestResultSet=60000;
    
    
    public function getRecsPerPage() {
        return $this->recsPerPage;
    }
    
    public function getBiggestResultSet() {
        return $this->biggestResultSet;
    }

    
}
