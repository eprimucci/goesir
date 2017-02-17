<?php
namespace AppBundle\Helper;

/**
 * 
 *
 * @author piri
 */
class StringHelper {

    
    //static $letras=['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
    
    
    static function isValidAdvancedSearchCondition($condition) {
        /**
         [
                {
                  "ctype": "answer",
                  "op": 0,
                  "pa": "Servicio",
                  "fid": "56e044372cb5dd76115eae34",
                  "fn": "Formulario QH889-2",
                  "q": "Pasa servicio o Falla",
                  "qtype": 1
                }
              ]
         */
        if( !isset($condition->ctype) || 
            !isset($condition->op) || 
            !isset($condition->pa) || 
            !isset($condition->fid) || 
            !isset($condition->fn) || 
            !isset($condition->q) || 
            !isset($condition->qtype)) {
            return false;                                
        }
        return true;
        
    }
    
    
    
    
    
    
    /**
     * Checks for valid JSON format
     * @see http://stackoverflow.com/a/6041773/1596796
     * @param type $json_string
     * @return boolean
     */
    static public function isJson($json_string) {
        if(is_array($json_string)) {
            return false;
        }
        return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/',
       preg_replace('/"(\\.|[^"\\\\])*"/', '', $json_string));
    }
    
    static public function contains($haystack, $needle) {
        if (strpos($haystack, $needle) !== false) {
            return true;
        }
        return false;
    }
    
    static public function startsWith($haystack, $needle) {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

    static public function endsWith($haystack, $needle) {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

    
    static function getTimeZones() {
        return array(
            
        'Pacific/Midway'=> '(GMT-11:00) Midway Island, Samoa',
        'America/Adak'=> '(GMT-10:00) Hawaii-Aleutian',
        'Pacific/Marquesas'=> '(GMT-09:30) Marquesas Islands',
        'Pacific/Gambier'=> '(GMT-09:00) Gambier Islands',
        'America/Anchorage'=> '(GMT-09:00) Alaska',
        'America/Ensenada'=> '(GMT-08:00) Tijuana, Baja California',
        'Etc/GMT+8'=> '(GMT-08:00) Pitcairn Islands',
        'America/Los_Angeles'=> '(GMT-08:00) Pacific Time (US & Canada)',
        'America/Denver'=> '(GMT-07:00) Mountain Time (US & Canada)',
        'America/Chihuahua'=> '(GMT-07:00) Chihuahua, La Paz, Mazatlan',
        'America/Dawson_Creek'=> '(GMT-07:00) Arizona',
        'America/Belize'=> '(GMT-06:00) Saskatchewan, Central America',
        'America/Cancun'=> '(GMT-06:00) Guadalajara, Mexico City, Monterrey',
        'Chile/EasterIsland'=> '(GMT-06:00) Easter Island',
        'America/Chicago'=> '(GMT-06:00) Central Time (US & Canada)',
        'America/New_York'=> '(GMT-05:00) Eastern Time (US & Canada)',
        'America/Havana'=> '(GMT-05:00) Cuba',
        'America/Bogota'=> '(GMT-05:00) Bogota, Lima, Quito, Rio Branco',
        'America/Caracas'=> '(GMT-04:30) Caracas',
        'America/Santiago'=> '(GMT-04:00) Santiago',
        'America/La_Paz'=> '(GMT-04:00) La Paz',
        'Atlantic/Stanley'=> '(GMT-04:00) Malvinas Islands',
        'America/Campo_Grande'=> '(GMT-04:00) Brazil',
        'America/Goose_Bay'=> '(GMT-04:00) Atlantic Time (Goose Bay)',
        'America/Glace_Bay'=> '(GMT-04:00) Atlantic Time (Canada)',
        'America/St_Johns'=> '(GMT-03:30) Newfoundland',
        'America/Araguaina'=> '(GMT-03:00) UTC-3',
        'America/Montevideo'=> '(GMT-03:00) Montevideo',
        'America/Miquelon'=> '(GMT-03:00) Miquelon, St. Pierre',
        'America/Godthab'=> '(GMT-03:00) Greenland',
        'America/Argentina/Buenos_Aires'=> '(GMT-03:00) Buenos Aires',
        'America/Sao_Paulo'=> '(GMT-03:00) Brasilia',
        'America/Noronha:'=>'(GMT-02:00) Mid-Atlantic',
        'Atlantic/Cape_Verde'=> '(GMT-01:00) Cape Verde Is.',
        'Atlantic/Azores'=> '(GMT-01:00) Azores',
        'Europe/Belfast'=> '(GMT) Greenwich Mean Time : Belfast',
        'Europe/Dublin'=> '(GMT) Greenwich Mean Time : Dublin',
        'Europe/Lisbon'=> '(GMT) Greenwich Mean Time : Lisbon',
        'Europe/London'=> '(GMT) Greenwich Mean Time : London',
        'Africa/Abidjan'=> '(GMT) Monrovia, Reykjavik',
        'Europe/Amsterdam'=> '(GMT+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
        'Europe/Belgrade'=> '(GMT+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague',
        'Africa/Algiers'=> '(GMT+01:00) West Central Africa',
        'Africa/Windhoek'=> '(GMT+01:00) Windhoek',
        'Asia/Beirut'=> '(GMT+02:00) Beirut',
        'Africa/Cairo'=> '(GMT+02:00) Cairo',
        'Asia/Gaza'=> '(GMT+02:00) Gaza',
        'Africa/Blantyre'=> '(GMT+02:00) Harare, Pretoria',
        'Asia/Jerusalem'=> '(GMT+02:00) Jerusalem',
        'Europe/Minsk'=> '(GMT+02:00) Minsk',
        'Asia/Damascus'=> '(GMT+02:00) Syria',
        'Europe/Moscow'=> '(GMT+03:00) Moscow, St. Petersburg, Volgograd',
        'Africa/Addis_Ababa'=> '(GMT+03:00) Nairobi',
        'Asia/Tehran'=> '(GMT+03:30) Tehran',
        'Asia/Dubai'=> '(GMT+04:00) Abu Dhabi, Muscat',
        'Asia/Yerevan'=> '(GMT+04:00) Yerevan',
        'Asia/Kabul'=> '(GMT+04:30) Kabul',
        'Asia/Yekaterinburg'=> '(GMT+05:00) Ekaterinburg',
        'Asia/Tashkent'=> '(GMT+05:00) Tashkent',
        'Asia/Kolkata'=> '(GMT+05:30) Chennai, Kolkata, Mumbai, New Delhi',
        'Asia/Katmandu'=> '(GMT+05:45) Kathmandu',
        'Asia/Dhaka'=> '(GMT+06:00) Astana, Dhaka',
        'Asia/Novosibirsk'=> '(GMT+06:00) Novosibirsk',
        'Asia/Rangoon'=> '(GMT+06:30) Yangon (Rangoon)',
        'Asia/Bangkok'=> '(GMT+07:00) Bangkok, Hanoi, Jakarta',
        'Asia/Krasnoyarsk'=> '(GMT+07:00) Krasnoyarsk',
        'Asia/Hong_Kong'=> '(GMT+08:00) Beijing, Chongqing, Hong Kong, Urumqi',
        'Asia/Irkutsk'=> '(GMT+08:00) Irkutsk, Ulaan Bataar',
        'Australia/Perth'=>'(GMT+08:00) Perth',
        'Australia/Eucla'=> '(GMT+08:45) Eucla',
        'Asia/Tokyo'=> '(GMT+09:00) Osaka, Sapporo, Tokyo',
        'Asia/Seoul'=> '(GMT+09:00) Seoul',
        'Asia/Yakutsk'=> '(GMT+09:00) Yakutsk',
        'Australia/Adelaide'=> '(GMT+09:30) Adelaide',
        'Australia/Darwin'=> '(GMT+09:30) Darwin',
        'Australia/Brisbane'=> '(GMT+10:00) Brisbane',
        'Australia/Hobart'=> '(GMT+10:00) Hobart',
        'Asia/Vladivostok'=> '(GMT+10:00) Vladivostok',
        'Australia/Lord_Howe'=> '(GMT+10:30) Lord Howe Island',
        'Etc/GMT-11'=> '(GMT+11:00) Solomon Is., New Caledonia',
        'Asia/Magadan'=> '(GMT+11:00) Magadan',
        'Pacific/Norfolk'=> '(GMT+11:30) Norfolk Island',
        'Asia/Anadyr'=> '(GMT+12:00) Anadyr, Kamchatka',
        'Pacific/Auckland'=> '(GMT+12:00) Auckland, Wellington',
        'Etc/GMT-12'=> '(GMT+12:00) Fiji, Kamchatka, Marshall Is.',
        'Pacific/Chatham'=> '(GMT+12:45) Chatham Islands',
        'Pacific/Tongatapu'=> '(GMT+13:00) Nuku Alofa',
        'Pacific/Kiritimati]'=> '(GMT+14:00) Kiritimati'            
        );
                
    }    
    
    
    
    static function extractNameAndEmail($email_string) {
        // note: this function is used to parse data generated ONLY by this app. is not an RFC 822 compliant pattern. we don't care.
        // our token is {space}{lt}
        $split = explode(' <', $email_string);
        
        if(count($split)==0) {
            $email = trim($email_string, '<>');
            $name = $email;
        }
        elseif(count($split)==1) {
            $email = trim($email_string, '<>');
            $name = $email;
        }
        else {
            $name = $split[0];
            $email = trim($split[1], '<>');
        }
        return array('name'=>$name, 'email'=>$email);
    }


    /**
     * Gets an array of strings and returns an array with those strings that are emails
     * @param array $emails
     * @return array
     */
    static function filterInvalidEmails(array $emails) {
        $res=array();
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $res[]=$email;
            }
        }
        return $res;
    }    
    
    
    static function is_valid_email_address($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return true;
        }
        return false;
        
    }

//If you would like to convert numbers into just the uppercase alphabet base and vice-versa (e.g. the column names in a Microsoft Windows Excel sheet..A-Z, AA-ZZ, AAA-ZZZ, ...), the following functions will do this.

    /**
     * Converts an integer into the alphabet base (A-Z).
     *
     * @param int $n This is the number to convert.
     * @return string The converted number.
     * @author Theriault
     * 
     */
    static function excelNum2alpha($n) {
        $r = '';
        for ($i = 1; $n >= 0 && $i < 10; $i++) {
            $r = chr(0x41 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
            $n -= pow(26, $i);
        }
        return $r;
    }

    /**
     * Converts an alphabetic string into an integer.
     *
     * @param int $n This is the number to convert.
     * @return string The converted number.
     * @author Theriault
     * 
     */
    static function excelAlpha2num($a) {
        $r = 0;
        $l = strlen($a);
        for ($i = 0; $i < $l; $i++) {
            $r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x40);
        }
        return $r - 1;
    }

    //Microsoft Windows Excel stops at IV (255), but this function can handle much larger. However, English words will start to form after a while and some may be offensive, so be careful.    

    
    /**
     * 
     * @param type $testDate
     * @return boolean
     */
    static function validateDate($testDate) {
        $testArray  = explode('-', $testDate);
        if (count($testArray) == 3) {
            if (checkdate($testArray[1], $testArray[2], $testArray[0])) {
                return true;
            } else {
                // problem with dates ...
                return false;
            }
        } else {
            // problem with input ...
            return false;
        }        
    }
    
    
    
    
    /**
     * Returns a valid file name from any user input
     * @param type $str
     * @return type
     */
    public static function normalizeFileNameString($str) {
        $str = strip_tags($str);
        $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
        $str = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $str);
        //$str = strtolower($str);
        $str = html_entity_decode($str, ENT_QUOTES, "utf-8");
        $str = htmlentities($str, ENT_QUOTES, "utf-8");
        $str = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $str);
        $str = str_replace(' ', '-', $str);
        $str = rawurlencode($str);
        $str = str_replace('%', '-', $str);
        return basename($str);
    }

}
