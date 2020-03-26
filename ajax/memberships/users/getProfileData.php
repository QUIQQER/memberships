<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Get all MembershipUser Objects data for the current session user (for frontend)
 *
 * @return array - view data for all relevant MembershipUser objects
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_getProfileData',
    function () {
        $SessionUser = QUI::getUserBySession();

        try {
            $membershipUsers = MembershipUsersHandler::getInstance()
                ->getMembershipUsersByUserId($SessionUser->getId());

            $data = [];

            foreach ($membershipUsers as $MembershipUser) {
                $data[] = $MembershipUser->getFrontendViewData();
            }

            // put default membership last
            $DefaultMembership = MembershipsHandler::getDefaultMembership();

            if ($DefaultMembership !== false) {
                $defaultId = $DefaultMembership->getId();

                usort($data, function ($a, $b) use ($defaultId) {
                    if ($a['membershipId'] == $defaultId) {
                        return 1;
                    }

                    if ($b['membershipId'] == $defaultId) {
                        return -1;
                    }

                    return 0;
                });
            }
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

            return [];
        }

        return $data;
    },
    [],
    'Permission::checkUser'
);
