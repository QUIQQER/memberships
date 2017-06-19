<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Add user(s) to a QUIQQER membership
 *
 * @param int $membershipId - Membership ID
 * @param array $userIds - QUIQQER user IDs
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_create',
    function ($membershipId, $userIds) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();
            $userIds         = json_decode($userIds, true);

            foreach ($userIds as $userId) {
                $MembershipUsers->createChild(array(
                    'membershipId' => (int)$membershipId,
                    'userId'       => (int)$userId
                ));
            }
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.create.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_create');
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

            return false;
        }

        $Membership = MembershipsHandler::getInstance()->getChild((int)$membershipId);

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/memberships',
                'message.ajax.memberships.users.create.success',
                array(
                    'membershipId'    => $Membership->getId(),
                    'membershipTitle' => $Membership->getTitle()
                )
            )
        );

        return true;
    },
    array('membershipId', 'userIds'),
    'Permission::checkAdminUser'
);
