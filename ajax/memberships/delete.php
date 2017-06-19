<?php

use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Delete multiple memberships
 *
 * @param array $membershipsIds - Membership IDs
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_delete',
    function ($membershipIds) {
        try {
            $membershipIds = json_decode($membershipIds, true);
            $Memberships   = new MembershipsHandler();

            foreach ($membershipIds as $membershipId) {
                $Membership = $Memberships->getChild((int)$membershipId);
                $Membership->delete();
            }
        } catch (QUI\Memberships\Exception $Exception) {
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
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_delete');
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
    array('membershipIds'),
    'Permission::checkAdminUser'
);
