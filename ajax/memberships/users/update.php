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
            $MembershipUser     = $MembershipUsers->getChild((int)$membershipUserId);
            $attributes         = json_decode($attributes, true);
            $updated            = [];
            $sendAutoExtendMail = false;

            foreach ($attributes as $k => $v) {
                switch ($k) {
                    case 'beginDate':
                    case 'endDate':
                        $v      = Utils::getFormattedTimestamp(strtotime($v));
                        $oldVal = $MembershipUser->getAttribute($k);

                        $updated[$k] = $oldVal.' => '.$v;

                        if ($k === 'endDate') {
                            $oldEndDate = strtotime($MembershipUser->getAttribute('endDate'));
                            $newEndDate = strtotime($v);
                            $now        = time();

                            if ($newEndDate >= $now && $newEndDate > $oldEndDate) {
                                $sendAutoExtendMail = true;
                            }
                        }
                        break;

                    case 'cancelled':
                        $v = boolval($v);

                        if ($v !== $MembershipUser->isCancelled()) {
                            if ($v === true) {
                                $MembershipUser->addHistoryEntry(
                                    MembershipUsersHandler::HISTORY_TYPE_CANCEL_BY_EDIT
                                );

                                /**
                                 * If an administrator cancels a membership for a user
                                 * the 'cancelEndDate' attribute is always equivalent to the
                                 * 'endDate'.
                                 *
                                 * This means that a period of notice that may be considered
                                 * if a contract is connected to the MembershipUser is NOT
                                 * considered here.
                                 */
                                $MembershipUser->setAttributes([
                                    'cancelStatus'  => MembershipUsersHandler::CANCEL_STATUS_CANCELLED_BY_SYSTEM,
                                    'cancelEndDate' => $MembershipUser->getAttribute('endDate')
                                ]);

                                QUI::getEvents()->fireEvent('quiqqerMembershipsCancelAdmin', [$MembershipUser]);
                            } else {
                                $MembershipUser->addHistoryEntry(
                                    MembershipUsersHandler::HISTORY_TYPE_UNCANCEL_BY_EDIT
                                );

                                $MembershipUser->setAttributes([
                                    'cancelStatus'  => MembershipUsersHandler::CANCEL_STATUS_NOT_CANCELLED,
                                    'cancelEndDate' => null
                                ]);
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

            if ($sendAutoExtendMail) {
                $MembershipUser->sendManualExtendMail();
            }
        } catch (QUI\Memberships\Exception $Exception) {
            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.memberships.users.update.error',
                    [
                        'error' => $Exception->getMessage()
                    ]
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
                'message.ajax.memberships.users.update.success',
                [
                    'membershipUserId'   => $MembershipUser->getId(),
                    'membershipUserName' => $MembershipUser->getUser()->getName()
                ]
            )
        );

        return true;
    },
    ['membershipUserId', 'attributes'],
    'Permission::checkAdminUser'
);
