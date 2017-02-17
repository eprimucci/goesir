<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ODM\Document(
 * collection="imagery", 
 * repositoryClass="AppBundle\Repository\ImageryRepository")
 */
class Imagery {

    /**
     * @ODM\Id
     */
    private $id;

    /**
     * @ODM\Field(type="string", name="image_name")
     *
     * @var string
     */
    private $imageName;
    
    
    /**
     * @ODM\Date
     */
    private $created;

    /**
     * @ODM\Date
     */
    private $updated;

    /**
     * @ODM\Date
     * @ODM\Index(name="inspectable_lastinspected")
     */
    private $lastInspected;

    function __construct() {
        $this->created = new \MongoDate();
    }
    
    
}
