<?php

namespace AppBundle\Helper;

use AppBundle\Document\MediaFile;

/**
 * These are the mime types we use for Inspectables
 *
 * @author piri
 */
class MimeTypeHelper {
    
    private static $mimeTypes = array(
        'application/msword'=>array('cat'=>MediaFile::OTHER, 'en'=>'', 'es'=>'',),
        'application/octet-stream'=>array('cat'=>MediaFile::OTHER, 'en'=>'', 'es'=>'',),
        'application/pdf'=>array('cat'=>MediaFile::PDF, 'en'=>'', 'es'=>'', ),
        'application/postscript'=>array('cat'=>MediaFile::OTHER, 'en'=>'', 'es'=>'',),
        'application/rtf'=>array('cat'=>MediaFile::OTHER, 'en'=>'', 'es'=>'',),
        'audio/x-aiff'=>array('cat'=>MediaFile::AUDIO, 'en'=>'', 'es'=>'',),
        'audio/basic'=>array('cat'=>MediaFile::AUDIO, 'en'=>'', 'es'=>'',),
        'audio/x-midi'=>array('cat'=>MediaFile::AUDIO, 'en'=>'', 'es'=>'',),
        'audio/x-wav'=>array('cat'=>MediaFile::AUDIO, 'en'=>'', 'es'=>'',),
        
        'image/bmp'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        'image/png'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        'image/gif'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        'image/jpeg'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        'image/tiff'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        'image/x-xbitmap'=>array('cat'=>MediaFile::IMAGE, 'en'=>'', 'es'=>'',),
        
        'video/mpeg'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/quicktime'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-msvideo'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/3gpp'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/3gpp2'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/h261'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/h263'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/h264'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/jpeg'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/jpm'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/mj2'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/mp4'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/mpeg'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/ogg'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/quicktime'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.dece.hd'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.dece.mobile'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.dece.pd'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.dece.sd'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.dece.video'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.fvt'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.mpegurl'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.ms-playready.media.pyv'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.uvvu.mp4'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/vnd.vivo'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/webm'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-f4v'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-fli'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-flv'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-m4v'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-ms-asf'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-ms-wm'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-ms-wmv'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-ms-wmx'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-ms-wvx'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-msvideo'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        'video/x-sgi-movie'=>array('cat'=>MediaFile::VIDEO, 'en'=>'', 'es'=>'',),
        
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>array('cat'=>MediaFile::DOCUMENT, 'en'=>'', 'es'=>'',),

        'application/vnd.ms-excel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/msexcel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/x-msexcel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/x-ms-excel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/x-excel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/x-dos_ms_excel'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/xls'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/x-xls'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/vnd.ms-office'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        'application/vnd.oasis.opendocument.spreadsheet'=>array('cat'=>MediaFile::EXCEL, 'en'=>'', 'es'=>'',),
        
        
        
        
        
        
        
    );
    
    static function getMimeTypes() {
        return self::mimeTypes;
    }

    /**
     * Do we accept this particular mime type?
     * @param string $mime
     * @return boolean
     */
    static function isAcceptable($mime) {
        return in_array($mime, array_keys(self::$mimeTypes));
    }
    
    static function getCategory($mime) {
        $type=  self::$mimeTypes[$mime];
        if($type!=null) {
            return $type['cat'];
        }
        return MediaFile::OTHER;
    }
    
    
    
}
