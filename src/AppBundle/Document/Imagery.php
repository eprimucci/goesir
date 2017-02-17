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
     * @ODM\Field(type="string")
     * @var string
     */
    private $storage;
    
    /**
     * @ODM\Date
     * @ODM\Index(name="imagery_dated")
     */
    private $dated;

    /**
     * @ODM\Field(type="string", name="sdated")
     * @ODM\Index(name="imagery_sdated")
     * @var string
     */
    private $originalDate;

    
    /**
     * @ODM\Date
     * @ODM\Index(name="imagery_downloaded")
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

    function getStorage() {
        return $this->storage;
    }

    function getDated() {
        return $this->dated;
    }

    function getOriginalDate() {
        return $this->originalDate;
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

    function setStorage($storage) {
        $this->storage = $storage;
    }

    function setDated($dated) {
        $this->dated = $dated;
    }

    function setOriginalDate($originalDate) {
        $this->originalDate = $originalDate;
    }

    function setDownloaded($downloaded) {
        $this->downloaded = $downloaded;
    }

    function setUpdated($updated) {
        $this->updated = $updated;
    }


    
}
