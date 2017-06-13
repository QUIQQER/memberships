<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Utils;

/**
 * Update a membership
 *
 * @param int $id - Membership ID
 * @param array $attributes - Update attributes
 * @return array|false - License data or false on error
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_update',
    function ($id, $attributes) {
        try {
            $Memberships = MembershipsHandler::getInstance();
            /** @var \QUI\Memberships\Membership $Membership */
            $Membership = $Memberships->getChild((int)$id);

            if ($Membership->isLocked()) {
                throw new QUI\Memberships\Exception(array(
                    'quiqqer/memberships',
                    'exception.membership.cannot.update.when.locked'
                ));
            }

            $attributes = json_decode($attributes, true);

            foreach ($attributes as $k => $v) {
                switch ($k) {
                    // do not clean content - contains HTML
                    case 'content':
                        break;

                    default:
                        $attributes[$k] = Utils::clearJSONString($v);
                }
            }

            $Membership->setAttributes($attributes);
            $Membership->update();
        } catch (QUI\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.update.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_update');
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
                'message.ajax.memberships.update.success',
                array(
                    'id'    => $Membership->getId(),
                    'title' => $Membership->getTitle()
                )
            )
        );

        return true;
    },
    array('id', 'attributes'),
    'Permission::checkAdminUser'
);
