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

    
    /**
     * Sorts the incoming IDs of filter objects so they can be
     * used in a querybuilder
     * @param type $filters
     * @return type
     */
    public function sortFilters($filters) {
        $people = array();
        $inspectables = array();
        $forms=array();
        $tags = array();
        $alerts = array();
        $strings = array();

        foreach ($filters as $filter) {
            switch ($filter->type) {

                case 'people':
                    $people[] = $filter->iid;
                    break;
                case 'inspectable':
                    $inspectables[] = $filter->iid;
                    break;
                case 'tag':
                    $tags[] = $filter->iid;
                    break;
                case 'alert':
                    $alerts[] = $filter->iid;
                    break;
                case 'form':
                    $forms[] = $filter->iid;
                    break;
                case 'string':
                    $strings = array($filter->iid); // no matter how many... just the last one! hack...
                    break;
            }
        }
        return array(
            'people' => $people,
            'inspectables' => $inspectables,
            'tags' => $tags,
            'alerts' => $alerts,
            'strings' => $strings,
            'forms' => $forms,
        );
    }

    
}
