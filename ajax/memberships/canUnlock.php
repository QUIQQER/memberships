<?php

use QUI\Permissions\Permission;
use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Checks if a user has the necessary permisssions to unlock a locked membership panel
 *
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_canUnlock',
    function () {
        return Permission::hasPermission(
            MembershipsHandler::PERMISSION_FORCE_EDIT,
            QUI::getUserBySession()
        );
    },
    array(),
    'Permission::checkAdminUser'
);
