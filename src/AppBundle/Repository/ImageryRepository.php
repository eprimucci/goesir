<?php

namespace AppBundle\Repository;

class ImageryRepository extends BaseRepository {

    public function getByFilenameAndDate($fileName, $date) {
        $qb = $this->createQueryBuilder();
        $qb->field('image_name')->equals($fileName);
        $qb->field('dated')->equals($date);
        return $qb->getQuery()->getSingleResult();
    }
    
    
    public function getDownloadPending() {
        $qb = $this->createQueryBuilder();
        $qb->field('stored')->equals(false);
        return $qb->getQuery()->execute();
    }
    
    public function getAnalysysPending() {
        $qb = $this->createQueryBuilder()
                ->select('id','sdated','image_name','dated')
                ->field('stored')->equals(true)
                ->field('analyzed')->equals(false)
                ->sort('sdated');
        return $qb->getQuery()->execute();
    }

}
