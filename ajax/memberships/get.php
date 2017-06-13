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
        $Memberships = MembershipsHandler::getInstance();
        $attributes  = $Memberships->getChild((int)$id)->getAttributes();

        $attributes['groupIds'] = trim($attributes['groupIds'], ',');

        return $attributes;
    },
    array('id'),
    'Permission::checkAdminUser'
);
