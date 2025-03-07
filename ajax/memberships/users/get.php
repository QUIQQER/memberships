<?php

/**
 * Get general data of a MembershipUser
 *
 * @param int $membershipUserId
 * @return array|false - history data or false on error
 */

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_get',
    function ($membershipUserId) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();
            /** @var MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild($membershipUserId);
            return $MembershipUser->getBackendViewData();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_getHistory');
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.general.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        }
    },
    ['membershipUserId'],
    'Permission::checkAdminUser'
);
