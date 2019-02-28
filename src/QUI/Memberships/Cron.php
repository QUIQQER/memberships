<?php

namespace QUI\Memberships;

use QUI;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;

/**
 * Class Cron
 *
 * Automatically checks memberships and cancels or extends them
 */
class Cron
{
    public static function checkMembershipUsers()
    {
        $MembershipUsers = MembershipUsersHandler::getInstance();

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from'   => $MembershipUsers->getDataBaseTableName(),
            'where'  => [
                'archived' => 0
            ]
        ]);

        $now = time();

        foreach ($result as $row) {
            try {
                /** @var MembershipUser $MembershipUser */
                $MembershipUser = $MembershipUsers->getChild($row['id']);
                $Membership     = $MembershipUser->getMembership();

                try {
                    $MembershipUser->getUser();
                } catch (QUI\Users\Exception $Exception) {
                    // archive MembershipUser if QUIQQER User cannot be found
                    if ($Exception->getCode() === 404) {
                        $MembershipUser->archive(MembershipUsersHandler::ARCHIVE_REASON_USER_DELETED);
                    }

                    continue;
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
                    $MembershipUser->extend();
                    continue;
                }

                // expire membership
                $MembershipUser->expire();
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(
                    self::class.' :: checkMembershipUsers() -> '.$Exception->getMessage()
                );
            }

        }
    }
}
