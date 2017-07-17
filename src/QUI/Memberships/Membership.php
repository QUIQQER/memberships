<?php

namespace QUI\Memberships;

use function PHPSTORM_META\type;
use QUI;
use QUI\CRUD\Child;
use QUI\ERP\Products\Handler\Products;
use QUI\Locale;
use QUI\Lock\Locker;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Permissions\Permission;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Products\Search\BackendSearch;
use QUI\Memberships\Products\MembershipField;
use QUI\ERP\Products\Handler\Products as ProductsHandler;
use QUI\ERP\Products\Handler\Fields as ProductFields;

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
     * Check if this membership is auto-extended
     *
     * @return bool
     */
    public function isAutoExtend()
    {
        return $this->getAttribute('autoExtend') ? true : false;
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
     * Delete membership
     *
     * Only possible if membership has no users in it
     *
     * @return void
     * @throws QUI\Memberships\Exception
     */
    public function delete()
    {
        Permission::checkPermission(Handler::PERMISSION_DELETE);

        $MembershipUsers = MembershipUsersHandler::getInstance();

        if (count($MembershipUsers->getIdsByMembershipId($this->id))) {
            throw new Exception(array(
                'quiqqer/memberships',
                'exception.membership.cannot.delete.with.users.left'
            ));
        }

        // @todo quiqqer/products abhandeln
        // @todo quiqqer/contracts abhandeln

        parent::delete();
    }

    /**
     * Get a user of this membership (non-archived)
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
                'userId'       => $userId,
                'archived'     => 0
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
     * Checks if this membership has an (active, non-archived) user assigned
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
                'userId'       => $userId,
                'archived'     => 0
            )
        ));

        return current(current($result)) > 0;
    }

    /**
     * Search membership users
     *
     * @param array $searchParams
     * @param bool $archivedOnly (optional) - search archived users only [default: false]
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return int[]|int - membership user IDs or count
     */
    public function searchUsers($searchParams, $archivedOnly = false, $countOnly = false)
    {
        $membershipUserIds = array();
        $Grid              = new QUI\Utils\Grid($searchParams);
        $gridParams        = $Grid->parseDBParams($searchParams);
        $tbl               = MembershipUsersHandler::getInstance()->getDataBaseTableName();
        $usersTbl          = QUI::getDBTableName('users');
        $binds             = array();
        $where             = array();

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT `musers`.id";
        }

        $sql .= " FROM `" . $tbl . "` musers, `" . $usersTbl . "` users";

        $where[] = '`musers`.userId = `users`.id';
        $where[] = '`musers`.membershipId = ' . $this->id;

        if ($archivedOnly === false) {
            $where[] = '`musers`.archived = 0';
        } else {
            $where[] = '`musers`.archived = 1';
        }

        if (!empty($searchParams['search'])) {
            $whereOR = array();

            $searchColumns = array(
                '`users`.username',
                '`users`.firstname',
                '`users`.lastname'
            );

            foreach ($searchColumns as $tbl => $column) {
                $whereOR[]       = $column . ' LIKE :search';
                $binds['search'] = array(
                    'value' => '%' . $searchParams['search'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }

            $where[] = '(' . implode(' OR ', $whereOR) . ')';
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])
        ) {
            $sortOn = Orthos::clear($searchParams['sortOn']);

            switch ($sortOn) {
                case 'username':
                case 'firstname':
                case 'lastname':
                    $sortOn = '`users`.' . $sortOn;
                    break;

                default:
                    $sortOn = '`musers`.' . $sortOn;
            }

            $order = "ORDER BY " . $sortOn;

            if (isset($searchParams['sortBy']) &&
                !empty($searchParams['sortBy'])
            ) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        }

        // LIMIT
        if (!empty($gridParams['limit'])
            && !$countOnly
        ) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT " . (int)20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: searchUsers() -> ' . $Exception->getMessage()
            );

            return array();
        }

        if ($countOnly) {
            return (int)current(current($result));
        }

        foreach ($result as $row) {
            $membershipUserIds[] = (int)$row['id'];
        }

        return $membershipUserIds;
    }

    /**
     * Calculate the end date for this membership based on a given time
     *
     * @param int $start (optional) - UNIX timestamp; if omitted use time()
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

        $durationMode = Handler::getSetting('durationMode');

        switch ($durationMode) {
            case 'day':
                $endTime    = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);
                $beginOfDay = strtotime("midnight", $endTime);
                $end        = strtotime("tomorrow", $beginOfDay) - 1;
                break;

            default:
                $end = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);
        }

        return Utils::getFormattedTimestamp($end);
    }

    /**
     * Requires: quiqqer/products
     *
     * Get all products that have this membership assigned
     *
     * @return QUI\ERP\Products\Product\Product[]
     */
    public function getProducts()
    {
        if (!in_array('quiqqer/products', Handler::getInstalledMembershipPackages())) {
            return array();
        }

        $Search = new BackendSearch();

        try {
            $result = $Search->search(array(
                'fields' => array(
                    MembershipField::FIELD_ID => "$this->id"
                )
            ));
        } catch (QUI\Permissions\Exception $Exception) {
            return array();
        }

        $products = array();

        foreach ($result as $id) {
            $products[] = ProductsHandler::getProduct($id);
        }

        return $products;
    }

    /**
     * Requires: quiqqer/products
     *
     * Create a Product from this Membership
     *
     * Hint: Every time this method is called, a new Product is created, regardless
     * of previous calls!
     *
     * @return QUI\ERP\Products\Product\Product|false
     */
    public function createProduct()
    {
        if (!in_array('quiqqer/products', Handler::getInstalledMembershipPackages())) {
            return false;
        }

        $categories = array();
        $fields     = array();

        $Category = Handler::getProductCategory();

        if ($Category) {
            $categories[] = $Category;
        }

        $Field = Handler::getProductField();

        if ($Field) {
            $Field->setValue($this->id);
            $fields[] = $Field;
        }

        // set title and description
        $TitleField  = ProductFields::getField(ProductFields::FIELD_TITLE);
        $DescField   = ProductFields::getField(ProductFields::FIELD_SHORT_DESC);
        $title       = json_decode($this->getAttribute('title'), true);
        $description = json_decode($this->getAttribute('description'), true);

        if (!empty($title)) {
            $TitleField->setValue($title);
            $fields[] = $TitleField;
        }

        if (!empty($description)) {
            $DescField->setValue($description);
            $fields[] = $DescField;
        }

        $Product = ProductsHandler::createProduct($categories, $fields);

        if (!empty($categories)) {
            $Product->setMainCategory($categories[0]);
            $Product->save();
        }

        return $Product;
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

    /**
     * Get membership data for backend view/edit purposes
     *
     * @return array
     */
    public function getBackendViewData()
    {
        return array(
            'id'          => $this->getId(),
            'title'       => $this->getTitle(),
            'description' => $this->getDescription(),
            'content'     => $this->getContent()
        );
    }
}
