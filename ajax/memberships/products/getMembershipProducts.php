<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get list of Products that have a specific Membership assigned
 *
 * @param int $membershipId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_products_getMembershipProducts',
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
