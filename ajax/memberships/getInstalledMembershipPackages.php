<?php

/**
 * Get all installed packages that are relevant for quiqqer/memberships
 *
 * @return array
 */

use QUI\Memberships\Utils;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getInstalledMembershipPackages',
    function () {
        return Utils::getInstalledMembershipPackages();
    },
    [],
    'Permission::checkAdminUser'
);
