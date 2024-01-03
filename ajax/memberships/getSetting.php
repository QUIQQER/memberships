<?php

/**
 * Get membership setting
 *
 * @return mixed
 */

use QUI\Memberships\Handler as MembershipsHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getSetting',
    function ($key) {
        return MembershipsHandler::getSetting($key);
    },
    ['key'],
    'Permission::checkAdminUser'
);
