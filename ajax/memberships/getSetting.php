<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get membership setting
 *
 * @return mixed
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getSetting',
    function ($key) {
        return MembershipsHandler::getSetting($key);
    },
    array('key'),
    'Permission::checkAdminUser'
);
