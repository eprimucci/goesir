<?php

namespace AppBundle\Repository;

class ImageryRepository extends BaseRepository {

    public function getByFilenameAndDate($fileName, $date) {

        $qb = $this->createQueryBuilder();

        $qb->field('fileName')->equals($fileName);
        $qb->field('downloadDate')->equals($date);

        return $qb->getQuery()->getSingleResult();
    }

}
