<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get data of single membership
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_get',
    function ($id) {
        $Memberships = new MembershipsHandler();
        return $Memberships->getChild((int)$id)->getAttributes();
    },
    array('id'),
    'Permission::checkAdminUser'
);
