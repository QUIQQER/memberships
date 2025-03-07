<?php

namespace QUI\Memberships\Users;

use Exception;
use QUI;
use QUI\CRUD\Child;
use QUI\CRUD\Factory;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;
use QUI\Permissions\Permission;

use function is_string;

class Handler extends Factory
{
    /**
     * Extend modes
     *
     * Determines how the cycle begin date is set if a membership user is extended
     */
    const EXTEND_MODE_RESET = 'reset';
    const EXTEND_MODE_PROLONG = 'prolong';

    /**
     * Duration modes
     *
     * Determines how exact membership user dates are calculated
     */
    const DURATION_MODE_DAY = 'day';
    const DURATION_MODE_EXACT = 'exact';

    /**
     * History entry types
     */
    const HISTORY_TYPE_CREATED = 'created';
    const HISTORY_TYPE_UPDATED = 'updated';
    const HISTORY_TYPE_CANCEL_BY_EDIT = 'cancel_by_edit';
    const HISTORY_TYPE_UNCANCEL_BY_EDIT = 'uncancel_by_edit';
    const HISTORY_TYPE_CANCEL_START = 'cancel_start';
    const HISTORY_TYPE_CANCEL_START_SYSTEM = 'cancel_system';
    const HISTORY_TYPE_CANCEL_ABORT_START = 'cancel_abort_start';
    const HISTORY_TYPE_CANCEL_ABORT_CONFIRM = 'cancel_abort_confirm';
    const HISTORY_TYPE_CANCEL_CONFIRM = 'cancel_confirm';
    const HISTORY_TYPE_CANCELLED = 'cancelled';
    const HISTORY_TYPE_EXPIRED = 'expired';
    const HISTORY_TYPE_DELETED = 'deleted';
    const HISTORY_TYPE_ARCHIVED = 'archived';
    const HISTORY_TYPE_EXTENDED = 'extended';
    const HISTORY_TYPE_MISC = 'misc';

    /**
     * Archive reasons
     */
    const ARCHIVE_REASON_CANCELLED = 'cancelled';
    const ARCHIVE_REASON_EXPIRED = 'expired';
    const ARCHIVE_REASON_DELETED = 'deleted';
    const ARCHIVE_REASON_USER_DELETED = 'user_deleted';

    /**
     * Cancel statusses
     */
    const CANCEL_STATUS_NOT_CANCELLED = 0;
    const CANCEL_STATUS_CANCEL_CONFIRM_PENDING = 1;
    const CANCEL_STATUS_ABORT_CANCEL_CONFIRM_PENDING = 2;
    const CANCEL_STATUS_CANCELLED = 3;
    const CANCEL_STATUS_CANCELLED_BY_SYSTEM = 4;

    /**
     * User attributes
     */
    const USER_ATTR_CANCEL_REMINDER_SENT = 'quiqqer.memberships.cancel_reminder_sent';

    /**
     * Permissions
     */
    const PERMISSION_EDIT_USERS = 'quiqqer.memberships.edit_users';

    public function getChild(int | string $id): MembershipUser
    {
        /* @var $MembershipUser MembershipUser */
        $MembershipUser = parent::getChild($id);

        // @phpstan-ignore-next-line
        return $MembershipUser;
    }

    /**
     * @param array $data
     * @param User|null $PermissionUser
     * @return Child
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
     * @throws QUI\Memberships\Exception
     * @throws QUI\Permissions\Exception
     */
    public function createChild(
        array $data = [],
        null | QUI\Interfaces\Users\User $PermissionUser = null
    ): QUI\CRUD\Child {
        if (is_null($PermissionUser)) {
            $PermissionUser = QUI::getUserBySession();
        }

        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS, $PermissionUser);

        $data['addedDate'] = Utils::getFormattedTimestamp();

        // user
        if (empty($data['userId'])) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.handler.no.user'
            ]);
        }

        // membership
        if (empty($data['membershipId'])) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.handler.no.membership'
            ]);
        }

        $Membership = MembershipsHandler::getInstance()->getChild($data['membershipId']);
        $User = QUI::getUsers()->get($data['userId']);

        if (!($Membership instanceof QUI\Memberships\Membership)) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.handler.no.membership'
            ]);
        }

        // if the user is already in the membership -> extend runtime
        if ($Membership->hasMembershipUserId($User->getId())) {
            $MembershipUser = $Membership->getMembershipUser($User->getId());
            $MembershipUser->extend(false);

            return $MembershipUser;
        }

        // current begin and end
        $data['beginDate'] = Utils::getFormattedTimestamp();
        $data['endDate'] = $Membership->calcEndDate();

        $data['extendCounter'] = 0;
        $data['cancelDate'] = null;
        $data['cancelEndDate'] = null;
        $data['cancelled'] = 0;
        $data['cancelStatus'] = 0;
        $data['archived'] = 0;
        $data['archiveDate'] = null;
        $data['archiveReason'] = null;
        $data['history'] = null;
        $data['extraData'] = null;
        $data['productId'] = null;
        $data['contractId'] = null;

        /** @var MembershipUser $NewChild */
        $NewChild = parent::createChild($data);
        $NewChild->setEditUser($PermissionUser);

        $NewChild->addHistoryEntry(self::HISTORY_TYPE_CREATED);
        $NewChild->addToGroups();
        $NewChild->update();

        QUI::getEvents()->fireEvent('quiqqerMembershipsUserCreate', [$NewChild]);

        return $NewChild;
    }

    /**
     * Get all MembershipUser IDs of membership users by Membership ID
     *
     * @param int $membershipId
     * @param bool $includeArchived (optional) - include archived MembershipUsers
     * @return int[]
     */
    public function getIdsByMembershipId(int $membershipId, bool $includeArchived = false): array
    {
        $where = [
            'membershipId' => $membershipId,
            'archived' => 0
        ];

        if ($includeArchived === true) {
            unset($where['archived']);
        }

        try {
            $result = QUI::getDataBase()->fetch([
                'select' => [
                    'id'
                ],
                'from' => MembershipUsersHandler::getDataBaseTableName(),
                'where' => $where
            ]);
        } catch (QUI\Exception $e) {
            QUI\System\Log::addError($e->getMessage());
            return [];
        }

        $membershipUserIds = [];

        foreach ($result as $row) {
            $membershipUserIds[] = $row['id'];
        }

        return $membershipUserIds;
    }

    /**
     * Get all MembershipUser objects by userId
     *
     * @param int|string $userId - QUIQQER User ID
     * @param bool $includeArchived (optional) - include archived MembershipUsers
     * @return MembershipUser[]
     */
    public function getMembershipUsersByUserId(int | string $userId, bool $includeArchived = false): array
    {
        if (is_string($userId)) {
            try {
                $userId = QUI::getUsers()->get($userId)->getId();
            } catch (QUI\Exception) {
            }
        }

        $where = [
            'userId' => $userId,
            'archived' => 0
        ];

        if ($includeArchived === true) {
            unset($where['archived']);
        }

        try {
            $result = QUI::getDataBase()->fetch([
                'select' => [
                    'id'
                ],
                'from' => self::getDataBaseTableName(),
                'where' => $where
            ]);
        } catch (QUI\Exception $e) {
            QUI\System\Log::addError($e->getMessage());
            return [];
        }

        $membershipUsers = [];

        foreach ($result as $row) {
            try {
                $membershipUsers[] = self::getChild($row['id']);
            } catch (QUI\Exception) {
            }
        }

        return $membershipUsers;
    }

    /**
     * Get MembershipUser of associated contract
     *
     * @param int $contractId
     * @return MembershipUser|false
     */
    public function getMembershipUserByContractId(int $contractId): bool | MembershipUser
    {
        try {
            $result = QUI::getDataBase()->fetch([
                'select' => [
                    'id'
                ],
                'from' => self::getDataBaseTableName(),
                'where' => [
                    'contractId' => $contractId
                ]
            ]);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        if (empty($result)) {
            return false;
        }

        try {
            /** @var MembershipUser $MembershipUser */
            $MembershipUser = self::getChild($result[0]['id']);
            return $MembershipUser;
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

//    /**
//     * Get membership
//     *
//     * @param int $id
//     * @return MembershipUser
//     * @throws QUI\Exception
//     */
//    public function getChild($id)
//    {
//        $childClass = $this->getChildClass();
//
//        $result = QUI::getDataBase()->fetch(array(
//            'from'  => $this->getDataBaseTableName(),
//            'where' => array(
//                'id'       => $id,
//                'archived' => 0
//            )
//        ));
//
//        if (!isset($result[0])) {
//            throw new QUI\Exception(
//                array(
//                    'quiqqer/system',
//                    'crud.child.not.found'
//                ),
//                404
//            );
//        }
//
//        $Child = new $childClass($result[0]['id'], $this);
//
//        if ($Child instanceof QUI\CRUD\Child) {
//            $Child->setAttributes($result[0]);
//        }
//
//        return $Child;
//    }

    /**
     * @return string
     */
    public function getDataBaseTableName(): string
    {
        return 'quiqqer_memberships_users';
    }

    /**
     * @return string
     */
    public function getChildClass(): string
    {
        return MembershipUser::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes(): array
    {
        return [
            'membershipId',
            'userId',
            'addedDate',
            'beginDate',
            'endDate',
            'archived',
            'history',
            'cancelDate',
            'cancelEndDate',
            'cancelled',
            'cancelStatus',
            'archiveReason',
            'archiveDate',
            'extraData',
            'productId',
            'contractId'
        ];
    }

    /**
     * Get config entry for a membershipusers config
     *
     * @param string $key
     * @return array|string
     *
     * @throws QUI\Exception
     */
    public static function getSetting(string $key): array | string
    {
        $Config = QUI::getPackage('quiqqer/memberships')->getConfig();
        return $Config->get('membershipusers', $key);
    }

    /**
     * Get membership extend mode
     *
     * see self::EXTEND_MODE_*
     *
     * @return string
     */
    public static function getExtendMode(): string
    {
        try {
            return self::getSetting('extendMode');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return self::EXTEND_MODE_PROLONG;
        }
    }

    /**
     * Get membership duration mode
     *
     * see self::DURATION_MODE_*
     *
     * @return string
     */
    public static function getDurationMode(): string
    {
        try {
            return QUI\Memberships\Handler::getSetting('durationMode');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return self::DURATION_MODE_DAY;
        }
    }
}
