<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Utils\Security\Orthos;

/**
 * Create a new QUIQQER membership
 *
 * @param string $title - Membership title
 * @param array $groupIds - IDs of all groups belonging to the new membership
 * @return integer|false - ID of new membership or false on error
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_create',
    function ($title, $groupIds) {
        try {
            $Memberships = MembershipsHandler::getInstance();

            /** @var \QUI\Memberships\Membership $NewMembership */
            $NewMembership = $Memberships->createChild(array(
                'title'    => Orthos::clear($title),
                'groupIds' => Orthos::clearArray(json_decode($groupIds, true))
            ));
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.create.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
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
                'message.ajax.memberships.create.success',
                array(
                    'title' => $title
                )
            )
        );

        return $NewMembership->getId();
    },
    array('title', 'groupIds'),
    'Permission::checkAdminUser'
);
