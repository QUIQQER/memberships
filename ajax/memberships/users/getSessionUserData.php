<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;

/**
 * Get all MembershipUser Objects data for the current session user
 *
 * @return array - view data for all relevant MembershipUser objects
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_getSessionUserData',
    function () {
        $SessionUser = QUI::getUserBySession();

        try {
            $membershipUsers = MembershipUsersHandler::getInstance()
                ->getMembershipUsersByUserId($SessionUser->getId());

            $data = array();

            foreach ($membershipUsers as $MembershipUser) {
                $data[] = $MembershipUser->getFrontendViewData();
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_getHistory');
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.general.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return array();
        }

        return $data;
    },
    array(),
    'Permission::checkAdminUser'
);
