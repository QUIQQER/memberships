<?php

namespace QUI\Memberships;

use DateInterval;
use QUI;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;

use function date_create;

/**
 * Class Cron
 *
 * Automatically checks memberships and cancels or extends them
 */
class Cron
{
    public static function checkMembershipUsers(): void
    {
        $MembershipUsers = MembershipUsersHandler::getInstance();

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from' => $MembershipUsers->getDataBaseTableName(),
            'where' => [
                'archived' => 0
            ]
        ]);

        $now = time();
        $cancelConfirmReminderAfterDays = (int)MembershipUsersHandler::getSetting('cancelReminderDays');
        $Now = date_create();
        $isLinkedToContracts = Handler::isLinkedToContracts();

        foreach ($result as $row) {
            try {
                /** @var MembershipUser $MembershipUser */
                $MembershipUser = $MembershipUsers->getChild($row['id']);
                $Membership = $MembershipUser->getMembership();

                try {
                    $MembershipUser->getUser();
                } catch (QUI\Users\Exception $Exception) {
                    // archive MembershipUser if QUIQQER User cannot be found
                    if ($Exception->getCode() === 404) {
                        $MembershipUser->archive(MembershipUsersHandler::ARCHIVE_REASON_USER_DELETED);
                    }

                    continue;
                }

                // Check if cancellation of membership has been started but NOT yet confirmed.
                // Send reminder e-mail after X days of unconfirmed cancellation.
                if (
                    !empty($cancelConfirmReminderAfterDays)
                    && (int)$MembershipUser->getAttribute(
                        'cancelStatus'
                    ) === MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING
                ) {
                    $CancelDate = date_create($MembershipUser->getAttribute('cancelDate'));

                    if ($CancelDate) {
                        $RemindDate = $CancelDate->add(new DateInterval('P' . $cancelConfirmReminderAfterDays . 'D'));
                        $User = $MembershipUser->getUser();

                        if (
                            !$User->getAttribute(MembershipUsersHandler::USER_ATTR_CANCEL_REMINDER_SENT)
                            && $Now > $RemindDate
                        ) {
                            $sent = $MembershipUser->sendConfirmCancelReminderMail();

                            if ($sent) {
                                $User->setAttribute(MembershipUsersHandler::USER_ATTR_CANCEL_REMINDER_SENT, true);
                                $User->save(QUI::getUsers()->getSystemUser());
                            }
                        }
                    }
                }

                // never expire a membership with infinite duration
                if ($Membership->isInfinite()) {
                    continue;
                }

                // check if membership has expired
                $endTimestamp = strtotime($MembershipUser->getAttribute('endDate'));

                if ($now <= $endTimestamp) {
                    continue;
                }

                // if membership has been cancelled -> archive it immediately
                if ($MembershipUser->isCancelled()) {
                    $cancelEndDate = $MembershipUser->getAttribute('cancelEndDate');
                    $cancelEndTime = strtotime($cancelEndDate);

                    if ($now >= $cancelEndTime) {
                        $MembershipUser->cancel();
                        continue;
                    }
                }

                // extend if membership is extended automatically
                if ($Membership->isAutoExtend()) {
                    // Only extend if not extended by contract
                    if (!$isLinkedToContracts || !$MembershipUser->getContractId()) {
                        $MembershipUser->extend();
                    }
                    continue;
                }

                // expire membership
                $MembershipUser->expire();
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    self::class . ' :: checkMembershipUsers() -> ' . $Exception->getMessage()
                );

                QUI\System\Log::writeException($Exception);
            }
        }
    }
}
