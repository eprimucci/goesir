<?php

namespace AppBundle\Repository;

use AppBundle\Document\Imagery;
use AppBundle\Helper\MongoResult,
    AppBundle\Helper\MongoHelper;

class ImageryRepository extends BaseRepository {

    /**
     * Get Inspectables tagged $tag
     * @param \AppBundle\Repository\Tag $tag
     * @return MongoCursor
     */
    public function getByTagReference(Tag $tag) {
        return $this->createQueryBuilder()
                        ->field('tags')
                        ->references($tag)
                        ->getQuery()
                        ->execute();
    }
    

    public function countTotal(UserDocument $user) {
        return $this->createQueryBuilder()
                        ->field('owner')->references($user->getCurrentCustomer())
                        ->count()->getQuery()->execute();
    }

    public function countPendingInspection(UserDocument $user) {
        return $this->createQueryBuilder()
                        ->field('owner')->references($user->getCurrentCustomer())
                        ->field('next_ins.due_date')->lte(new \DateTime())
                        ->count()->getQuery()->execute();
    }
    
    /**
     * Search method for when using the filter widget
     * @param \AppBundle\Document\UserDocument $user
     * @param type $data
     * @return MongoResult
     */
    public function searchWidget(UserDocument $user, $data = null) {

        $mongoResult = new MongoResult;
        
        $qb = $this->createQueryBuilder();

        // just the requesting user/customer objects
        $qb->field('owner')->references($user->getCurrentCustomer());

        // do we have filters?
        if ($data != null && is_array($data->filters)) {
            $filters = $this->sortFilters($data->filters);
            if (count($filters['strings'])) {
                $qb->addOr($qb->expr()->field('name')->equals(new \MongoRegex('/^'.$filters['strings'][0].'.*/i')));
                $qb->addOr($qb->expr()->field('serialNumber')->equals(new \MongoRegex('/^'.$filters['strings'][0].'.*/i')));
            }
            if (count($filters['forms'])) {
                $qb->field('form_refs.form_id')->in($filters['forms']);
            }
            if (count($filters['inspectables'])) {
                $qb->field('id')->in($filters['inspectables']);
            }
            if (count($filters['tags'])) {
                $qb->field('tags.$id')->all(MongoHelper::MongoIdize($filters['tags']));
            }
        }

        $sort=array('name'=>'ASC', 'serialNumber'=>'ASC');
        foreach ($sort as $key => $value) {
            $qb->sort($key, $value);
        }

        // pending inspections
        if($data->overdue) {
            $qb->field('next_ins.due_date')->lte(new \DateTime());
        }
        
        $qb->skip($data->sr);
        
        if($data->limit) {
            $qb->limit($this->getRecsPerPage());
        }
        else {
            $qb->limit($this->getBiggestResultSet());
        }

        $mongoResult->setSortfields($sort);
        $mongoResult->setRecsPerPage($this->getRecsPerPage());
        $mongoResult->setCursor($qb->getQuery()->execute());
        $mongoResult->setTotalCount($mongoResult->getCursor()->count());
        $mongoResult->setStartRow($data->sr);
        $mongoResult->setEndRow(0);

        return $mongoResult;
    }

    

    
    public function getInspectablesUsingMediaFile($customer, $mediafileId) {
        $qb = $this->createQueryBuilder();
        $qb->field('owner')->references($customer);
        $qb->field('mediafile_refs.mediafile_id')->in(array($mediafileId));
        $sort=array('name'=>'ASC', 'serialNumber'=>'ASC');
        foreach ($sort as $key => $value) {
            $qb->sort($key, $value);
        }
        $qb->limit(500);
        return $qb->getQuery()->execute();
    }
    
    
}
