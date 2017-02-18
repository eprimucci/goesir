<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;


/**
 * @ODM\EmbeddedDocument
 */
class PixelData {

    /**
     * @ODM\Field(type="string")
     */
    private $imageryid;
    
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
    
    /**
     * @ODM\Field(type="int")
     */
    private $ts;
    
    function getImageryid() {
        return $this->imageryid;
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

    function getTs() {
        return $this->ts;
    }

    function setImageryid($imageryid) {
        $this->imageryid = $imageryid;
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

    function setTs($ts) {
        $this->ts = $ts;
    }




}
