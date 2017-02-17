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
     * @ODM\Index(name="imagery_filename")
     * @var string
     */
    private $imageName;
    
    
    /**
     * @ODM\Date
     * @ODM\Index(name="imagery_ddate")
     */
    private $downloaded;

    /**
     * @ODM\Date
     */
    private $updated;

    function __construct() {
    }
    
    function getId() {
        return $this->id;
    }

    function getImageName() {
        return $this->imageName;
    }

    function getDownloaded() {
        return $this->downloaded;
    }

    function getUpdated() {
        return $this->updated;
    }

    function setId($id) {
        $this->id = $id;
    }

    function setImageName($imageName) {
        $this->imageName = $imageName;
    }

    function setDownloaded($downloaded) {
        $this->downloaded = $downloaded;
    }

    function setUpdated($updated) {
        $this->updated = $updated;
    }


}
