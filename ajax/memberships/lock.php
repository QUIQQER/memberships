<?php

/**
 * Locks a membership panel if the user has the necessary permission(s)
 *
 * @param int $id - Membership ID
 * @param string $lockKey - Membership panel lock key
 * @return bool - success
 */

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Watcher;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_lock',
    function ($id) {
        try {
            $Memberships = MembershipsHandler::getInstance();
            $Membership = $Memberships->getChild((int)$id);

            if ($Membership->isLocked()) {
                $Membership->unlock();

                if (class_exists('QUI\Watcher')) {
                    Watcher::addString(
                        QUI::getLocale()->get(
                            'quiqqer/memberships',
                            'watcher.membership.force.edit',
                            [
                                'id' => $id
                            ]
                        ),
                        'package_quiqqer_memberships_ajax_memberships_lock'
                    );
                }
            }

            $Membership->lock();
        } catch (\QUI\Permissions\Exception $Exception) {
            throw $Exception;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_quiqqer_memberships_ajax_memberships_lock -> ' . $Exception->getMessage()
            );

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.general.error'
                )
            );

            return false;
        }

        return true;
    },
    ['id'],
    'Permission::checkAdminUser'
);
