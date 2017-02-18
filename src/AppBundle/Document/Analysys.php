<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(
 * collection="analysys", 
 * repositoryClass="AppBundle\Repository\AnalysysRepository")
 */
class Analysys {

    /**
     * @ODM\Id
     */
    private $id;

    /**
     * @ODM\ReferenceOne(targetDocument="Imagery")
     * @ODM\Index(name="analysys_imagery")
     * @var $imager Imagery
     */
    private $imagery;

    
    /**
     * @ODM\Field(type="int")
     * @ODM\Index(name="analysys_y")
     */
    private $pixely;

    /**
     * @ODM\Field(type="int")
     * @ODM\Index(name="analysys_x")
     */
    private $pixelx;
    
    
    /**
     * MongoDate parsed from $originalDate
     * @ODM\Date
     * @ODM\Index(name="analysys_when")
     * @var $dated \MongoDate
     */
    private $when;

    
    /**
     * @ODM\Field(type="int")
     */
    private $pxred;

    /**
     * @ODM\Field(type="int")
     */
    private $pxgreen;

    /**
     * @ODM\Field(type="int")
     */
    private $pxblue;
    
    /**
     * @ODM\Field(type="int")
     */
    private $alpha;

    /**
     * @ODM\Field(type="float")
     */
    private $pxrednormal;

    /**
     * @ODM\Field(type="float")
     */
    private $pxgreennormal;

    /**
     * @ODM\Field(type="float")
     */
    private $pxbluenormal;

    /**
     * @ODM\Field(type="float")
     */
    private $pxcyan;

    /**
     * @ODM\Field(type="float")
     */
    private $pxmagenta;

    /**
     * @ODM\Field(type="float")
     */
    private $pxyellow;

    /**
     * @ODM\Field(type="float")
     */
    private $pxblack;

    /**
     * @ODM\Field(type="float")
     */
    private $pxtotal;

    
    
    function __construct() {
        $this->when=new \MongoDate();
    }
    
    function getId() {
        return $this->id;
    }

    function getImagery() {
        return $this->imagery;
    }

    function getPixely() {
        return $this->pixely;
    }

    function getPixelx() {
        return $this->pixelx;
    }

    function getWhen() {
        return $this->when;
    }

    function getPxred() {
        return $this->pxred;
    }

    function getPxgreen() {
        return $this->pxgreen;
    }

    function getPxblue() {
        return $this->pxblue;
    }

    function getAlpha() {
        return $this->alpha;
    }

    function getPxrednormal() {
        return $this->pxrednormal;
    }

    function getPxgreennormal() {
        return $this->pxgreennormal;
    }

    function getPxbluenormal() {
        return $this->pxbluenormal;
    }

    function getPxcyan() {
        return $this->pxcyan;
    }

    function getPxmagenta() {
        return $this->pxmagenta;
    }

    function getPxyellow() {
        return $this->pxyellow;
    }

    function getPxblack() {
        return $this->pxblack;
    }

    function getPxtotal() {
        return $this->pxtotal;
    }

    function setId($id) {
        $this->id = $id;
    }

    function setImagery($imagery) {
        $this->imagery = $imagery;
    }

    function setPixely($pixely) {
        $this->pixely = $pixely;
    }

    function setPixelx($pixelx) {
        $this->pixelx = $pixelx;
    }

    function setWhen($when) {
        $this->when = $when;
    }

    function setPxred($pxred) {
        $this->pxred = $pxred;
    }

    function setPxgreen($pxgreen) {
        $this->pxgreen = $pxgreen;
    }

    function setPxblue($pxblue) {
        $this->pxblue = $pxblue;
    }

    function setAlpha($alpha) {
        $this->alpha = $alpha;
    }

    function setPxrednormal($pxrednormal) {
        $this->pxrednormal = $pxrednormal;
    }

    function setPxgreennormal($pxgreennormal) {
        $this->pxgreennormal = $pxgreennormal;
    }

    function setPxbluenormal($pxbluenormal) {
        $this->pxbluenormal = $pxbluenormal;
    }

    function setPxcyan($pxcyan) {
        $this->pxcyan = $pxcyan;
    }

    function setPxmagenta($pxmagenta) {
        $this->pxmagenta = $pxmagenta;
    }

    function setPxyellow($pxyellow) {
        $this->pxyellow = $pxyellow;
    }

    function setPxblack($pxblack) {
        $this->pxblack = $pxblack;
    }

    function setPxtotal($pxtotal) {
        $this->pxtotal = $pxtotal;
    }

        
        
}
