<?php

/**
 * Get data of single membership
 *
 * @return array
 */

use QUI\Memberships\Handler as MembershipsHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getView',
    function ($id) {
        $Memberships = MembershipsHandler::getInstance();
        $Membership = $Memberships->getChild((int)$id);
        return $Membership->getBackendViewData();
    },
    ['id'],
    'Permission::checkAdminUser'
);
