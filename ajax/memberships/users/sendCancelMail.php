<?php

/**
 * Send cancel email to user (manually)
 *
 * @param int $membershipUserId
 * @return bool - success
 */

use QUI\Memberships\Users\Handler as MembershipUsersHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_sendCancelMail',
    function ($membershipUserId) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();

            /** @var \QUI\Memberships\Users\MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild((int)$membershipUserId);
            $MembershipUser->sendConfirmCancelMail();
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.sendCancelMail.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_sendCancelMail');
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
                'message.ajax.memberships.users.sendCancelMail.success',
                [
                    'membershipUserId' => $MembershipUser->getId(),
                    'membershipUserName' => $MembershipUser->getUser()->getName()
                ]
            )
        );

        return true;
    },
    ['membershipUserId'],
    'Permission::checkAdminUser'
);
