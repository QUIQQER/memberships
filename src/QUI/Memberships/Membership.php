<?php

namespace QUI\Memberships;

use QUI;
use QUI\CRUD\Child;
use QUI\Locale;

class Membership extends Child
{
    /**
     * Get IDs of all QUIQQER Groups
     *
     * @return int[]
     */
    public function getGroupIds()
    {
        $groupIds = $this->getAttribute('groupIds');
        return explode(",", trim($groupIds, ","));
    }

    /**
     * Get membership title
     *
     * @param Locale $Locale (optional)
     * @return string - localized title
     */
    public function getTitle($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('title'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * Get membership description
     *
     * @param Locale $Locale (optional)
     * @return string - localized description
     */
    public function getDescription($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('description'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * Get membership content
     *
     * @param Locale $Locale (optional)
     * @return string - localized content
     */
    public function getContent($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('content'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function update()
    {
        // handle multilingual attributes ?


        parent::update();
    }
}
