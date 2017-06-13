<?php

namespace QUI\Memberships;

use QUI;
use QUI\Utils\Security\Orthos;

class Utils
{
    /**
     * Clear a JSON string from evil code but keep JSON intact
     *
     * @param string $str - JSON string
     * @return string - cleared JSON string
     */
    public static function clearJSONString($str)
    {
        $str = Orthos::removeHTML($str);
        $str = Orthos::clearPath($str);
//        $str = Orthos::clearFormRequest($str);

        $str = htmlspecialchars($str, ENT_NOQUOTES);

        return $str;
    }

    /**
     * Clear an array that contains JSON-strings
     *
     * @param array $array
     * @return array - cleared array
     */
    public static function clearArrayWithJSON(array $array)
    {
        if (!is_array($array)) {
            return array();
        }

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = self::clearArrayWithJSON($v);
                continue;
            }

            $array[$k] = self::clearJSONString($v);
        }

        return $array;
    }

    /**
     * Get translation from locale data array
     *
     * @param array $localeData
     * @return string
     */
    public static function getLocaleFromArray($localeData)
    {
        return QUI::getLocale()->get($localeData['group'], $localeData['var']);
    }

    /**
     * Get formatted timestamp for a given UNIX timestamp
     *
     * @param int $time (optional) - if omitted use time()
     * @return string
     */
    public static function getFormattedTimestamp($time = null)
    {
        if (is_null($time)) {
            $time = time();
        }

        return date('Y-m-d H:i:s', $time);
    }
}
