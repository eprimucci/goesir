<?php

namespace AppBundle\Repository;

class PixelRepository extends BaseRepository {

    public function findByCoordinates($x, $y) {
        $qb = $this->createQueryBuilder();
        $qb->field('pixely')->equals($y);
        $qb->field('pixelx')->equals($x);
        return $qb->getQuery()->getSingleResult();
    }
    
    

}
