<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get data of single membership for quiqqer/products fields
 *
 * @param int $membershipId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_products_getFieldData',
    function ($membershipId) {
        $Memberships = MembershipsHandler::getInstance();
        $Membership  = $Memberships->getChild((int)$membershipId);

        return array(
            'id'    => $Membership->getId(),
            'title' => $Membership->getTitle()
        );
    },
    array('membershipId'),
    'Permission::checkAdminUser'
);
