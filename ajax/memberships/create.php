<?php

/**
 * Create a new QUIQQER membership
 *
 * @param string $title - Membership title
 * @param array $groupIds - IDs of all groups belonging to the new membership
 * @return integer|false - ID of new membership or false on error
 */

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_create',
    function ($title, $groupIds) {
        try {
            $Memberships = MembershipsHandler::getInstance();

            /** @var \QUI\Memberships\Membership $NewMembership */
            $NewMembership = $Memberships->createChild([
                'title' => Orthos::clear($title),
                'groupIds' => Orthos::clearArray(json_decode($groupIds, true))
            ]);
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.create.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_create');
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
                'message.ajax.memberships.create.success',
                [
                    'title' => $title
                ]
            )
        );

        return $NewMembership->getId();
    },
    ['title', 'groupIds'],
    'Permission::checkAdminUser'
);
