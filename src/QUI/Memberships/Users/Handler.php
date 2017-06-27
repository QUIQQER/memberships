<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\CRUD\Factory;
use QUI\Memberships\Utils;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Permissions\Permission;

class Handler extends Factory
{
    const HISTORY_TYPE_CREATED          = 'created';
    const HISTORY_TYPE_UPDATED          = 'updated';
    const HISTORY_TYPE_CANCEL_BY_EDIT   = 'cancel_by_edit';
    const HISTORY_TYPE_UNCANCEL_BY_EDIT = 'uncancel_by_edit';
    const HISTORY_TYPE_CANCEL_START     = 'cancel_start';
    const HISTORY_TYPE_CANCEL_CONFIRM   = 'cancel_confirm';
    const HISTORY_TYPE_CANCELLED        = 'cancelled';
    const HISTORY_TYPE_EXPIRED          = 'expired';
    const HISTORY_TYPE_DELETED          = 'deleted';
    const HISTORY_TYPE_ARCHIVED         = 'archived';
    const HISTORY_TYPE_EXTENDED         = 'extended';

    const ARCHIVE_REASON_CANCELLED    = 'cancelled';
    const ARCHIVE_REASON_EXPIRED      = 'expired';
    const ARCHIVE_REASON_DELETED      = 'deleted';
    const ARCHIVE_REASON_USER_DELETED = 'user_deleted';

    const PERMISSION_EDIT_USERS = 'quiqqer.memberships.edit_users';

    /**
     * @inheritdoc
     * @throws QUI\Memberships\Exception
     */
    public function createChild($data = array())
    {
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS);

        $data['addedDate'] = Utils::getFormattedTimestamp();

        // user
        if (empty($data['userId'])) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.handler.no.user'
            ));
        }

        // membership
        if (empty($data['membershipId'])) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.handler.no.membership'
            ));
        }

        $Membership = MembershipsHandler::getInstance()->getChild($data['membershipId']);
        $User       = QUI::getUsers()->get($data['userId']);

        // if the user is already in the membership -> extend runtime
        if ($Membership->hasMembershipUserId($User->getId())) {
            $MembershipUser = $Membership->getMembershipUser($User->getId());
            $MembershipUser->extend(false);

            return $MembershipUser;
        }

        // current begin and end
        $data['beginDate'] = Utils::getFormattedTimestamp();
        $data['endDate']   = $Membership->calcEndDate();

        /** @var MembershipUser $NewChild */
        $NewChild = parent::createChild($data);
        $NewChild->addHistoryEntry(self::HISTORY_TYPE_CREATED);
        $NewChild->addToGroups();
        $NewChild->update();

        return $NewChild;
    }

    /**
     * Get all MembershipUser IDs of membership users by Membership ID
     *
     * @param int $membershipId
     * @return int[]
     */
    public function getIdsByMembershipId($membershipId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => MembershipUsersHandler::getDataBaseTableName(),
            'where'  => array(
                'membershipId' => $membershipId
            )
        ));

        $membershipUserIds = array();

        foreach ($result as $row) {
            $membershipUserIds[] = $row['id'];
        }

        return $membershipUserIds;
    }

    /**
     * Get all QUIQQER User IDs of membership users by Membership ID
     *
     * @param int $membershipId
     * @return int[]
     */
    public function getUserIdsByMembershipId($membershipId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'userId'
            ),
            'from'   => MembershipUsersHandler::getDataBaseTableName(),
            'where'  => array(
                'membershipId' => $membershipId
            )
        ));

        $membershipUserIds = array();

        foreach ($result as $row) {
            $membershipUserIds[] = $row['userId'];
        }

        return $membershipUserIds;
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
     * @inheritdoc
     * @return string
     */
    public function getDataBaseTableName()
    {
        return 'quiqqer_memberships_users';
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getChildClass()
    {
        return MembershipUser::class;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function getChildAttributes()
    {
        return array(
            'membershipId',
            'userId',
            'addedDate',
            'beginDate',
            'endDate',
            'archived',
            'history',
            'cancelHash',
            'cancelDate',
            'cancelled',
            'archiveReason',
            'archiveDate'
        );
    }

    /**
     * Get config entry for a membershipusers config
     *
     * @param string $key
     * @return array|string
     */
    public static function getSetting($key)
    {
        $Config = QUI::getPackage('quiqqer/memberships')->getConfig();
        return $Config->get('membershipusers', $key);
    }
}
