<?php
namespace AppBundle\Helper;

/**
 * 
 *
 * @author piri
 */
class MongoHelper {

    static function MongoIdize(array $ids) {
        $res = array();
        foreach ($ids as $id) {
            $res[] = new \MongoId($id);
        }
        return $res;
    }


    
    
}
