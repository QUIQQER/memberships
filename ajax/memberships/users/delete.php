<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Delete user(s) from a membership
 *
 * @param array $membershipsIds - Membership IDs
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_delete',
    function ($membershipId, $userIds) {
        try {
            $membershipIds = json_decode($membershipIds, true);
            $Memberships   = new MembershipsHandler();

            foreach ($membershipIds as $membershipId) {
                $Membership = $Memberships->getChild((int)$membershipId);
                $Membership->delete();
            }
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.delete.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
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
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        }

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/memberships',
                'message.ajax.memberships.delete.success'
            )
        );

        return true;
    },
    array('membershipId', 'userIds'),
    'Permission::checkAdminUser'
);