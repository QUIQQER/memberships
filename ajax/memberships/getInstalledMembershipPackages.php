<?php

use QUI\Memberships\Utils;

/**
 * Get all installed packages that are relevant for quiqqer/memberships
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getInstalledMembershipPackages',
    function () {
        return Utils::getInstalledMembershipPackages();
    },
    array(),
    'Permission::checkAdminUser'
);
