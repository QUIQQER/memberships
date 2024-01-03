<?php

/**
 * Get data of single membership
 *
 * @return array
 */

use QUI\Memberships\Handler as MembershipsHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_get',
    function ($id) {
        $Memberships = MembershipsHandler::getInstance();
        $Membership = $Memberships->getChild((int)$id);
        $attributes = $Membership->getAttributes();

        $attributes['groupIds'] = trim($attributes['groupIds'], ',');
        $attributes['uniqueGroupIds'] = $Membership->getUniqueGroupIds();

        return $attributes;
    },
    ['id'],
    'Permission::checkAdminUser'
);
