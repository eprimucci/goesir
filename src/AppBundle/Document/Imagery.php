<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

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
     * MongoDate parsed from $originalDate
     * @ODM\Date
     * @ODM\Index(name="imagery_dated")
     */
    private $dated;

    /**
     * String at the GOES archive website. Then parsed into MongoDate and stored in field $dated
     * @ODM\Field(type="string", name="sdated")
     * @ODM\Index(name="imagery_sdated")
     * @var string
     */
    private $originalDate;

    /**
     * Date of the actual storage in S3
     * @ODM\Date
     * @ODM\Index(name="imagery_download_date")
     */
    private $downloadDate;

    
    /**
     * When was this document updated
     * @ODM\Date
     */
    private $updated;
    
    
    /**
     * @ODM\Boolean
     * @ODM\Index(name="imagery_stored")
     */
    private $stored;
    
    
    /**
     * @ODM\Boolean
     * @ODM\Index(name="imagery_analyzed")
     */
    private $analyzed;
    

    

    function __construct() {
        $this->stored=false;
        $this->analyzed=false;
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

    function getDownloadDate() {
        return $this->downloadDate;
    }

    function getUpdated() {
        return $this->updated;
    }

    function getStored() {
        return $this->stored;
    }

    function getAnalyzed() {
        return $this->analyzed;
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

    function setDownloadDate($downloadDate) {
        $this->downloadDate = $downloadDate;
    }

    function setUpdated($updated) {
        $this->updated = $updated;
    }

    function setStored($stored) {
        $this->stored = $stored;
    }

    function setAnalyzed($analyzed) {
        $this->analyzed = $analyzed;
    }


        
}
