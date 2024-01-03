<?php

/**
 * Create a new Product from a Membership
 *
 * @param int $membershipId
 * @return bool - success
 */

use QUI\Memberships\Handler as MembershipsHandler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_products_createMembershipProducts',
    function ($membershipId) {
        try {
            $Memberships = MembershipsHandler::getInstance();
            $Membership = $Memberships->getChild((int)$membershipId);
            $Membership->createProduct();
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.products.createMembershipProducts.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'AJAX :: package_quiqqer_memberships_ajax_memberships_products_createMembershipProducts'
            );
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
                'message.ajax.memberships.products.createMembershipProducts.success'
            )
        );

        return true;
    },
    ['membershipId'],
    'Permission::checkAdminUser'
);
