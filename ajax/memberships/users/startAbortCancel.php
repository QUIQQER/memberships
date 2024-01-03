<?php

/**
 * Abort cancellation process
 *
 * @param int $membershipUserId
 * @return bool - success
 */

use QUI\Memberships\Users\Handler as MembershipUsersHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_startAbortCancel',
    function ($membershipUserId) {
        if (QUI::getUsers()->isNobodyUser(QUI::getUserBySession())) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.abortCancel.login.necessary'
                )
            );

            return false;
        }

        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();

            /** @var \QUI\Memberships\Users\MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild((int)$membershipUserId);
            $MembershipUser->startAbortCancel();
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.abortCancel.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_startAbortCancel');
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
                'message.ajax.memberships.users.abortCancel.success'
            )
        );

        return true;
    },
    ['membershipUserId'],
    'Permission::checkUser'
);
