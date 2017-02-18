<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(
 * collection="pixels", 
 * repositoryClass="AppBundle\Repository\PixelRepository")
 */
class Pixel {

    /**
     * @ODM\Id
     */
    private $id;

    /**
     * @ODM\Field(type="int")
     * @ODM\Index(name="pixel_y")
     */
    private $pixely;

    /**
     * @ODM\Field(type="int")
     * @ODM\Index(name="pixel_x")
     */
    private $pixelx;
    
    
    /**
     * @ODM\Date
     * @ODM\Index(name="pixel_when")
     * @var $when \DateTime
     */
    private $when;

    
    /**
     * @ODM\EmbedMany(targetDocument="PixelData") 
     */
    private $pixeldatum = [];


    function hasImagery($id) {
        /* @var $pixelData PixelData */
        foreach($this->getPixeldatum() as $pixelData) {
            if($pixelData->getImageryid()==$id) {
                return true;
            }
        }
        return false;
    }
    
    public function addPixelData(PixelData $pixelData) {
//        $sortedData=[];
//        /* @var $existingPixelData PixelData */
//        foreach($this->pixeldatum as $existingPixelData) {
//            if($pixelData->getTs() <= $existingPixelData->getTs()) {
//                $sortedData[]=$pixelData;
//            }
//            $sortedData[]=$existingPixelData;
//        }
        $this->pixeldatum[]=$pixelData;
    }
    
    function __construct() {
        $this->when=new \MongoDate();
    }
    
    
    public function getId() {
        return $this->id;
    }

    public function getPixely() {
        return $this->pixely;
    }

    public function getPixelx() {
        return $this->pixelx;
    }

    public function getWhen() {
        return $this->when;
    }

    public function getPixeldatum() {
        return $this->pixeldatum;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setPixely($pixely) {
        $this->pixely = $pixely;
        return $this;
    }

    public function setPixelx($pixelx) {
        $this->pixelx = $pixelx;
        return $this;
    }

    public function setWhen($when) {
        $this->when = $when;
        return $this;
    }

    public function setPixeldatum($pixeldatum) {
        $this->pixeldatum = $pixeldatum;
        return $this;
    }



    
    
}
