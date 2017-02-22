<?php

namespace AppBundle\Repository;

class ImageryRepository extends BaseRepository {

    public function findByFilenameAndDate($fileName, $date) {
        $qb = $this->createQueryBuilder();
        $qb->field('image_name')->equals($fileName);
        $qb->field('dated')->equals($date);
        return $qb->getQuery()->getSingleResult();
    }
    
    
    public function findByDownloadPending() {
        $qb = $this->createQueryBuilder();
        $qb->field('stored')->equals(false);
        $qb->field('avoid')->equals(false);
        return $qb->getQuery()->execute();
    }
    
    public function findByAnalysysPending() {
        $qb = $this->createQueryBuilder()
                ->select('id','sdated','image_name','dated')
                ->field('stored')->equals(true)
                ->field('analyzed')->equals(false)
                ->field('avoid')->equals(false)
                ->sort('sdated');
        return $qb->getQuery()->execute();
    }

}
