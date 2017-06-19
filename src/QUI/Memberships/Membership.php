<?php

namespace QUI\Memberships;

use QUI;
use QUI\CRUD\Child;
use QUI\Locale;
use QUI\Lock\Locker;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Permissions\Permission;

class Membership extends Child
{
    /**
     * Get IDs of all QUIQQER Groups
     *
     * @return int[]
     */
    public function getGroupIds()
    {
        $groupIds = $this->getAttribute('groupIds');
        return explode(",", trim($groupIds, ","));
    }

    /**
     * Get membership title
     *
     * @param Locale $Locale (optional)
     * @return string - localized title
     */
    public function getTitle($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('title'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * Get membership description
     *
     * @param Locale $Locale (optional)
     * @return string - localized description
     */
    public function getDescription($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('description'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * Get membership content
     *
     * @param Locale $Locale (optional)
     * @return string - localized content
     */
    public function getContent($Locale = null)
    {
        if (is_null($Locale)) {
            $Locale = QUI::getLocale();
        }

        $trans = json_decode($this->getAttribute('content'), true);

        if (isset($trans[$Locale->getCurrent()])) {
            return $trans[$Locale->getCurrent()];
        }

        return '';
    }

    /**
     * Check if this membership is auto-renewed
     *
     * @return bool
     */
    public function isAutoRenew()
    {
        return $this->getAttribute('autoRenew') ? true : false;
    }

    /**
     * @inheritdoc
     * @throws QUI\Memberships\Exception
     */
    public function update()
    {
        Permission::checkPermission(Handler::PERMISSION_EDIT);

        $attributes = $this->getAttributes();

        // check groups
        if (empty($attributes['groupIds'])
        ) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.handler.no.groups'
            ));
        }

        $attributes['groupIds'] = ',' . $attributes['groupIds'] . ',';

        // check duration
        $duration = explode('-', $attributes['duration']);

        if ($duration[0] < 1) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.membership.update.duration.invalid'
            ));
        }

        // edit user and timestamp
        $attributes['editUser'] = QUI::getUserBySession()->getId();
        $attributes['editDate'] = Utils::getFormattedTimestamp();

        $this->setAttributes($attributes);

        parent::update();
    }

    /**
     * Delete membership and remove all associated membership users
     *
     * @return void
     */
    public function delete()
    {
        Permission::checkPermission(Handler::PERMISSION_DELETE);

        $MembershipUsers = MembershipUsersHandler::getInstance();

        foreach ($MembershipUsers->getIdsByMembershipId($this->id) as $membershipUserId) {
            $MembershipUsers->getChild($membershipUserId)->delete();
        }

        parent::delete();
    }

    /**
     * Get a user of this membership
     *
     * @param int $userId - User ID
     * @return QUI\Memberships\Users\MembershipUser
     * @throws QUI\Memberships\Exception
     */
    public function getMembershipUser($userId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => MembershipUsersHandler::getInstance()->getDataBaseTableName(),
            'where'  => array(
                'membershipId' => $this->id,
                'userId'       => $userId
            )
        ));

        if (empty($result)) {
            throw new Exception(array(
                'quiqqer/memberships',
                'exception.membership.user.not.found',
                array(
                    'userId' => $userId
                )
            ), 404);
        }

        return MembershipUsersHandler::getInstance()->getChild($result[0]['id']);
    }

    /**
     * Get IDs of all QUIQQER Groups that are UNIQUE to this membership
     *
     * @return int[]
     */
    public function getUniqueGroupIds()
    {
        $Memberships    = Handler::getInstance();
        $groupIds       = $this->getGroupIds();
        $uniqueGroupIds = $groupIds;

        foreach ($Memberships->getMembershipIdsByGroupIds($groupIds) as $membershipId) {
            if ($membershipId == $this->getId()) {
                continue;
            }

            $Membership = $Memberships->getChild($membershipId);

            foreach ($Membership->getGroupIds() as $groupId) {
                if (in_array($groupId, $groupIds)) {
                    $k = array_search($groupId, $uniqueGroupIds);

                    if ($k !== false) {
                        unset($uniqueGroupIds[$k]);
                    }
                }
            }
        }

        return $uniqueGroupIds;
    }

    /**
     * Checks if this membership has a user assigned
     *
     * @param int $userId
     * @return bool
     */
    public function hasMembershipUserId($userId)
    {
        $result = QUI::getDataBase()->fetch(array(
            'count'  => 1,
            'select' => array(
                'id'
            ),
            'from'   => MembershipUsersHandler::getInstance()->getDataBaseTableName(),
            'where'  => array(
                'membershipId' => $this->id,
                'userId'       => $userId
            )
        ));

        return current(current($result)) > 0;
    }

    /**
     * Search memberships
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return int[] - membership user IDs
     */
    public function searchUsers($searchParams, $countOnly = false)
    {
        $membershipUserIds = array();
        $Grid              = new QUI\Utils\Grid($searchParams);
        $gridParams        = $Grid->parseDBParams($searchParams);
        $tbl               = MembershipUsersHandler::getInstance()->getDataBaseTableName();

        if ($countOnly) {
            $result = QUI::getDataBase()->fetch(array(
                'count' => 1,
                'from'  => $tbl,
                'where' => array(
                    'membershipId' => $this->id
                ),
            ));

            return current(current($result));
        }

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => $tbl,
            'where'  => array(
                'membershipId' => $this->id
            ),
            'limit'  => $gridParams['limit']
        ));

        foreach ($result as $row) {
            $membershipUserIds[] = (int)$row['id'];
        }

        return $membershipUserIds;
    }

    /**
     * Set all membership users to the assigned membership groups
     *
     * @return void
     */
    public function setUsersToGroups()
    {
        $MembershipUsers = MembershipUsersHandler::getInstance();
        $Users           = QUI::getUsers();
        $groupIds        = $this->getGroupIds();

        foreach ($MembershipUsers->getUserIdsByMembershipId($this->id) as $membershipUserId) {
            $User = $Users->get($membershipUserId);

            foreach ($groupIds as $groupId) {
                $User->addToGroup($groupId);
            }

            $User->save(QUI::getUsers()->getSystemUser());
        }
    }

    /**
     * Calculate the end date for this membership based on a given time
     *
     * @param int $start (optional) - UNIX timestamp; of omitted use time()
     * @return string - formatted timestamp
     */
    public function calcEndDate($start = null)
    {
        if (is_null($start)) {
            $start = time();
        }

        $start = Utils::getFormattedTimestamp($start);

        $duration      = explode('-', $this->getAttribute('duration'));
        $durationCount = $duration[0];
        $durationScope = $duration[1];

        $end = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);

        return Utils::getFormattedTimestamp($end);
    }

    /**
     * Locks editing of this membership for the current session user
     *
     * @return void
     */
    public function lock()
    {
        Locker::lock(QUI::getPackage('quiqqer/memberships'), $this->getLockKey());
    }

    /**
     * Unlock membership (requires permission!)
     *
     * @return void
     * @throws QUI\Permissions\Exception
     */
    public function unlock()
    {
        Locker::unlockWithPermissions(
            QUI::getPackage('quiqqer/memberships'),
            $this->getLockKey(),
            Handler::PERMISSION_FORCE_EDIT
        );
    }

    /**
     * Check if this membership is currently locked
     *
     * @return bool
     */
    public function isLocked()
    {
        return Locker::isLocked(QUI::getPackage('quiqqer/memberships'), $this->getLockKey());
    }

    /**
     * Get membership lock key
     *
     * @return string
     */
    protected function getLockKey()
    {
        return 'membership_' . $this->id;
    }
}
