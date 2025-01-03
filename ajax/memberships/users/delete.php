<?php

/**
 * Delete user(s) from a membership
 *
 * @param array $membershipsIds - Membership IDs
 * @return bool - success
 */

use QUI\Memberships\Users\Handler as MembershipUsersHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_delete',
    function ($userIds) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();
            $userIds = json_decode($userIds, true);

            foreach ($userIds as $userId) {
                $MembershipUsers->getChild($userId)->delete();
            }
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.delete.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_delete');
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

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/memberships',
                'message.ajax.memberships.users.delete.success'
            )
        );

        return true;
    },
    ['userIds'],
    'Permission::checkAdminUser'
);
