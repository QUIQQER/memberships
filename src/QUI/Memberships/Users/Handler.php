<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\CRUD\Factory;
use QUI\Memberships\Utils;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;

class Handler extends Factory
{
    /**
     * @inheritdoc
     * @throws QUI\Memberships\Exception
     */
    public function createChild($data = array())
    {
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

        if ($Membership->hasMembershipUserId($User->getId())) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.handler.user.is.already.in.membership',
                array(
                    'userId'   => $User->getId(),
                    'userName' => $User->getUsername()
                )
            ));
        }

        // current begin and end
        $data['beginDate'] = Utils::getFormattedTimestamp();
        $data['endDate']   = $Membership->calcEndDate();

        $NewChild = parent::createChild($data);

        $Membership->setUsersToGroups();

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
     * Get all QUIQQER IDs of membership users by Membership ID
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

    /**
     * Get membership
     *
     * @param int $id
     * @return MembershipUser
     */
    public function getChild($id)
    {
        return parent::getChild($id);
    }

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
            'endDate'
        );
    }
}
