<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;
use QUI\Memberships\Membership;

/**
 * Get all installed packages that are relevant for quiqqer/memberships
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getInstalledMembershipPackages',
    function () {
        return MembershipsHandler::getInstalledMembershipPackages();
    },
    array(),
    'Permission::checkAdminUser'
);
