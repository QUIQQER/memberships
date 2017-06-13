<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Lock\Locker;
use QUI\Watcher;

/**
 * Unlocks a location panel if the user has the necessary permission(s)
 *
 * @param string $lockKey - Location panel lock key
 * @return bool - success
 *
 * @throws \QUI\Permissions\Exception
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_unlock',
    function ($id) {
        try {
            $Memberships = MembershipsHandler::getInstance();
            /** @var \QUI\Memberships\Membership $Membership */
            $Membership = $Memberships->getChild((int)$id);

            if ($Membership->isLocked()) {
                $Membership->unlock();

                Watcher::addString(
                    QUI::getLocale()->get(
                        'quiqqer/memberships',
                        'watcher.location.force.edit',
                        array(
                            'id' => $Membership->getId()
                        )
                    ),
                    'package_quiqqer_memberships_ajax_memberships_lock'
                );
            } else {
                $Membership->unlock();
            }
        } catch (\QUI\Permissions\Exception $Exception) {
            return true;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_quiqqer_memberships_ajax_memberships_lock -> ' . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.memberships.general.error'
                )
            );

            return false;
        }

        return true;
    },
    array('id'),
    'Permission::checkAdminUser'
);
