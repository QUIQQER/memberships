<?php

namespace QUI\Memberships;

use QUI;
use QUI\Utils\Security\Orthos;

class Utils
{
    /**
     * Clears a JSON string from evil code but keep JSON intact
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

    /**
     * Get list of all packages that are relevant for quiqqer/memberships
     * and that are currently installed
     *
     * @return array
     */
    public static function getInstalledMembershipPackages()
    {
        $packages         = array();
        $relevantPackages = array(
            'quiqqer/products',
            'quiqqer/contracts'
        );

        foreach ($relevantPackages as $package) {
            try {
                QUI::getPackage($package);
                $packages[] = $package;
            } catch (\Exception $Exception) {
                // ignore (package is probably not installed)
            }
        }

        return $packages;
    }

    /**
     * Check if quiqqer/products is installed
     *
     * @return bool
     */
    public static function isQuiqqerProductsInstalled()
    {
        return QUI::getPackageManager()->isInstalled('quiqqer/products');
    }

    /**
     * Check if quiqqer/contracts is installed
     *
     * @return bool
     */
    public static function isQuiqqerContractsInstalled()
    {
        return QUI::getPackageManager()->isInstalled('quiqqer/contracts');
    }
}
