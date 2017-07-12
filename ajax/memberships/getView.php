<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get data of single membership
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getView',
    function ($id) {
        $Memberships = MembershipsHandler::getInstance();
        $Membership  = $Memberships->getChild((int)$id);
        return $Membership->getBackendViewData();
    },
    array('id'),
    'Permission::checkAdminUser'
);
