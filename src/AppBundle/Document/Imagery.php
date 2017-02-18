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
     * @var $dated \DateTime
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
     * @var $downloadDate \DateTime
     */
    private $downloadDate;

    
    /**
     * When was this document updated
     * @ODM\Date
     * @var $updated \DateTime
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
    

    /**
     * 
     * @ODM\Field(type="int", name="fsize")
     * @ODM\Index(name="imagery_fsize")
     * @var int
     */
    private $fileSize;    

    function __construct() {
        $this->stored=false;
        $this->analyzed=false;
    }
    
    
    public function getId() {
        return $this->id;
    }

    public function getImageName() {
        return $this->imageName;
    }

    public function getStorage() {
        return $this->storage;
    }

    public function getDated() {
        return $this->dated;
    }

    public function getOriginalDate() {
        return $this->originalDate;
    }

    public function getDownloadDate() {
        return $this->downloadDate;
    }

    public function getUpdated() {
        return $this->updated;
    }

    public function getStored() {
        return $this->stored;
    }

    public function getAnalyzed() {
        return $this->analyzed;
    }

    public function getFileSize() {
        return $this->fileSize;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setImageName($imageName) {
        $this->imageName = $imageName;
        return $this;
    }

    public function setStorage($storage) {
        $this->storage = $storage;
        return $this;
    }

    public function setDated($dated) {
        $this->dated = $dated;
        return $this;
    }

    public function setOriginalDate($originalDate) {
        $this->originalDate = $originalDate;
        return $this;
    }

    public function setDownloadDate($downloadDate) {
        $this->downloadDate = $downloadDate;
        return $this;
    }

    public function setUpdated($updated) {
        $this->updated = $updated;
        return $this;
    }

    public function setStored($stored) {
        $this->stored = $stored;
        return $this;
    }

    public function setAnalyzed($analyzed) {
        $this->analyzed = $analyzed;
        return $this;
    }

    public function setFileSize($fileSize) {
        $this->fileSize = $fileSize;
        return $this;
    }

    
    
        
}
