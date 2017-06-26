<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;

/**
 * Update a MembershipUser
 *
 * @param int $membershipUserId
 * @param array $attributes - Update attributes
 * @return bool - success
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_update',
    function ($membershipUserId, $attributes) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();
            /** @var \QUI\Memberships\Users\MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild((int)$membershipUserId);
            $attributes     = json_decode($attributes, true);
            $updated        = array();

            foreach ($attributes as $k => $v) {
                switch ($k) {
                    case 'beginDate':
                    case 'endDate':
                        $v      = Utils::getFormattedTimestamp(strtotime($v));
                        $oldVal = $MembershipUser->getAttribute($k);

                        if ($oldVal != $v) {
                            $updated[$k] = $oldVal . ' => ' . $v;
                        }
                        break;

                    case 'cancelled':
                        $v = boolval($v);

                        if ($v !== $MembershipUser->isCancelled()) {
                            if ($v === true) {
                                $MembershipUser->addHistoryEntry(
                                    MembershipUsersHandler::HISTORY_TYPE_CANCEL_BY_EDIT
                                );
                            } else {
                                $MembershipUser->addHistoryEntry(
                                    MembershipUsersHandler::HISTORY_TYPE_UNCANCEL_BY_EDIT
                                );
                            }
                        }
                        break;

                    default:
                        // do not update un-updatable attributes
                        continue 2;
                }

                $MembershipUser->setAttribute($k, $v);
            }

            if (!empty($updated)) {
                $MembershipUser->addHistoryEntry(
                    MembershipUsersHandler::HISTORY_TYPE_UPDATED,
                    json_encode($updated)
                );
            }

            $MembershipUser->update();
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.update.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_update');
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
                'message.ajax.memberships.users.update.success',
                array(
                    'membershipUserId'   => $MembershipUser->getId(),
                    'membershipUserName' => $MembershipUser->getUser()->getName()
                )
            )
        );

        return true;
    },
    array('membershipUserId', 'attributes'),
    'Permission::checkAdminUser'
);
