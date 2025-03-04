<?php

namespace QUI\Memberships;

use DateInterval;
use DateTime;
use QUI;
use QUI\Utils\Security\Orthos;

use function explode;
use function is_numeric;
use function is_string;
use function strtotime;

class Utils
{
    /**
     * Clears a JSON string from evil code but keep JSON intact
     *
     * @param string $str - JSON string
     * @return string - cleared JSON string
     */
    public static function clearJSONString(string $str): string
    {
        $str = Orthos::removeHTML($str);
        $str = Orthos::clearPath($str);
//        $str = Orthos::clearFormRequest($str);

        return htmlspecialchars($str, ENT_NOQUOTES);
    }

    /**
     * Clear an array that contains JSON-strings
     *
     * @param array $array
     * @return array - cleared array
     */
    public static function clearArrayWithJSON(array $array): array
    {
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
    public static function getLocaleFromArray(array $localeData): string
    {
        return QUI::getLocale()->get($localeData['group'], $localeData['var']);
    }

    /**
     * Get formatted timestamp for a given UNIX timestamp
     *
     * @param string|DateTime|int|null $time (optional) - Timestamp or \DateTime object [default: now]
     * @return string
     */
    public static function getFormattedTimestamp(null | string | DateTime | int $time = null): string
    {
        if (is_null($time)) {
            $time = time();
        }

        if ($time instanceof DateTime) {
            return $time->format('Y-m-d H:i:s');
        }

        if (is_string($time) && !is_numeric($time)) {
            $time = strtotime($time);
        }

        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Get list of all packages that are relevant for quiqqer/memberships
     * and that are currently installed
     *
     * @return array
     */
    public static function getInstalledMembershipPackages(): array
    {
        $packages = [];
        $relevantPackages = [
            'quiqqer/products',
            'quiqqer/contracts',
            'quiqqer/erp-plans'
        ];

        foreach ($relevantPackages as $package) {
            try {
                QUI::getPackage($package);
                $packages[] = $package;
            } catch (\Exception) {
                // ignore (package is probably not installed)
            }
        }

        return $packages;
    }

    /**
     * Parse a DateInterval from a contract duration setting
     *
     * @param string $duration
     * @return DateInterval|false
     * @throws \Exception
     */
    public static function parseIntervalFromDuration(string $duration): DateInterval | bool
    {
        if (empty($duration)) {
            return false;
        }

        $duration = explode('-', $duration);
        $intervalNumber = $duration[0];

        $intervalPeriod = match ($duration[1]) {
            'week' => 'W',
            'month' => 'M',
            'year' => 'Y',
            default => 'D',
        };

        return new DateInterval('P' . $intervalNumber . $intervalPeriod);
    }

    /**
     * Check if quiqqer/products is installed
     *
     * @return bool
     */
    public static function isQuiqqerProductsInstalled(): bool
    {
        return QUI::getPackageManager()->isInstalled('quiqqer/products');
    }

    /**
     * Check if quiqqer/erp-plans is installed
     *
     * @return bool
     */
    public static function isQuiqqerErpPlansInstalled(): bool
    {
        return QUI::getPackageManager()->isInstalled('quiqqer/erp-plans');
    }

    /**
     * Check if quiqqer/contracts is installed
     *
     * @return bool
     */
    public static function isQuiqqerContractsInstalled(): bool
    {
        return QUI::getPackageManager()->isInstalled('quiqqer/contracts');
    }
}
