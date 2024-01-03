<?php

/**
 * Checks if a user has the necessary permisssions to unlock a locked membership panel
 *
 * @return bool
 */

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Permissions\Permission;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_canUnlock',
    function () {
        return Permission::hasPermission(
            MembershipsHandler::PERMISSION_FORCE_EDIT,
            QUI::getUserBySession()
        );
    },
    [],
    'Permission::checkAdminUser'
);
