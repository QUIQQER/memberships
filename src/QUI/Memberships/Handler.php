<?php

namespace QUI\Memberships;

use QUI\CRUD\Factory;
use QUI\Utils\Grid;
use QUI;
use QUI\Permissions\Permission;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Utils\Security\Orthos;

class Handler extends Factory
{
    /**
     * quiqqer/memberships permissions
     */
    const PERMISSION_CREATE     = 'quiqqer.memberships.create';
    const PERMISSION_EDIT       = 'quiqqer.memberships.edit';
    const PERMISSION_DELETE     = 'quiqqer.memberships.delete';
    const PERMISSION_FORCE_EDIT = 'quiqqer.memberships.force_edit';

    /**
     * quiqqer/products field IDs
     */
    const PRODUCTS_FIELD_MEMBERSHIP     = 102;
    const PRODUCTS_FIELD_MEMBERSHIPFLAG = 103;

    /**
     * @inheritdoc
     * @throws QUI\Memberships\Exception
     */
    public function createChild($data = array())
    {
        Permission::checkPermission(self::PERMISSION_CREATE);

        $data['createDate'] = Utils::getFormattedTimestamp();
        $data['createUser'] = QUI::getUserBySession()->getId();

        // title
        $title = trim($data['title']);

        if (empty($title)) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.handler.no.title'
            ));
        }

        $data['title'] = array();

        foreach (QUI::availableLanguages() as $lang) {
            $data['title'][$lang] = $title;
        }

        $data['title'] = json_encode($data['title']);

        // groupIds
        $Groups   = QUI::getGroups();
        $groupIds = $data['groupIds'];

        if (empty($groupIds)
            || !is_array($groupIds)
        ) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.handler.no.groups'
            ));
        }

        foreach ($groupIds as $groupId) {
            // check if group exist by getting them
            $Groups->get((int)$groupId);
        }

        $data['groupIds'] = ',' . implode(',', $groupIds) . ',';
        $data['duration'] = '1-month';

        /** @var Membership $NewMembership */
        $NewMembership = parent::createChild($data);
        $NewMembership->createProduct();

        return $NewMembership;
    }

    /**
     * Get membership
     *
     * @param int $id
     * @return Membership
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
        return 'quiqqer_memberships';
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getChildClass()
    {
        return Membership::class;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function getChildAttributes()
    {
        return array(
            'title',
            'description',
            'content',
            'duration',
            'groupIds',
            'autoExtend',
            'editDate',
            'editUser',

            // these fields require quiqqer/order
            'paymentInterval'

            // @todo additional fields for quiqqer/contracts
        );
    }

    /**
     * Search memberships
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return array - membership IDs
     */
    public function search($searchParams, $countOnly = false)
    {
        $memberships = array();
        $Grid        = new Grid($searchParams);
        $gridParams  = $Grid->parseDBParams($searchParams);

        $binds = array();
        $where = array();

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT id";
        }

        $sql .= " FROM `" . $this->getDataBaseTableName() . "`";

        if (!empty($searchParams['userId'])) {
            $memberhsipUsers = MembershipUsersHandler::getInstance()->getMembershipUsersByUserId(
                (int)$searchParams['userId']
            );

            $membershipIds = array();

            /** @var QUI\Memberships\Users\MembershipUser $MembershipUser */
            foreach ($memberhsipUsers as $MembershipUser) {
                $membershipIds[] = $MembershipUser->getMembership()->getId();
            }

            if (!empty($membershipIds)) {
                $where[] = '`id` IN (' . implode(',', $membershipIds) . ')';
            }
        }

        if (!empty($searchParams['search'])) {
            $searchColumns = array(
                'title',
                'description',
                'content'
            );

            $whereOr = array();

            foreach ($searchColumns as $searchColumn) {
                $whereOr[] = '`' . $searchColumn . '` LIKE :search';
            }

            if (!empty($whereOr)) {
                $where[] = '(' . implode(' OR ', $whereOr) . ')';

                $binds['search'] = array(
                    'value' => '%' . $searchParams['search'] . '%',
                    'type'  => \PDO::PARAM_STR
                );
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])
        ) {
            $sortOn = Orthos::clear($searchParams['sortOn']);
            $order  = "ORDER BY " . $sortOn;

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
            $memberships[] = $row['id'];
        }

        return $memberships;
    }

    /**
     * Get IDs of all memberships that have specific groups assigned (OR)
     *
     * @param array $groupIds
     * @return int[]
     */
    public function getMembershipIdsByGroupIds($groupIds)
    {
        $ids = array();

        if (empty($groupIds)) {
            return $ids;
        }

        $sql = 'SELECT `id` FROM ' . self::getDataBaseTableName();
        $sql .= ' WHERE ';

        $whereOr = array();
        $binds   = array();

        foreach ($groupIds as $groupId) {
            $whereOr[]       = '`groupIds` LIKE :' . $groupId;
            $binds[$groupId] = array(
                'value' => '%,' . $groupId . ',%',
                'type'  => \PDO::PARAM_INT
            );
        }

        $sql .= implode(" OR ", $whereOr);

        $PDO  = QUI::getDataBase()->getPDO();
        $Stmt = $PDO->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return array();
        }

        foreach ($result as $row) {
            $ids[] = $row['id'];
        }

        return $ids;
    }

    /**
     * Get config entry for a membership setting
     *
     * @param string $key
     * @return mixed
     */
    public static function getSetting($key)
    {
        $Config = QUI::getPackage('quiqqer/memberships')->getConfig();
        return $Config->get('memberships', $key);
    }

    /**
     * Requires: quiqqer/products
     *
     * Get Memberships product category
     *
     * @return QUI\ERP\Products\Interfaces\CategoryInterface|false
     */
    public static function getProductCategory()
    {
        $Conf       = QUI::getPackage('quiqqer/memberships')->getConfig();
        $categoryId = $Conf->get('products', 'categoryId');

        if (empty($categoryId)) {
            return false;
        }

        try {
            return ProductCategories::getCategory((int)$categoryId);
        } catch (\Exception $Exception) {
            if ($Exception->getCode() !== 404) {
                QUI\System\Log::addError(self::class . ' :: getProductCategory()');
                QUI\System\Log::writeException($Exception);
            }

            return false;
        }
    }

    /**
     * Require: quiqqer/products
     *
     * Get quiqqer/products membership Field
     *
     * @return QUI\ERP\Products\Interfaces\FieldInterface|false
     */
    public static function getProductMembershipField()
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return false;
        }

        try {
            return ProductFields::getField(self::PRODUCTS_FIELD_MEMBERSHIP);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: getProductMembershipField()');
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Require: quiqqer/products
     *
     * Get quiqqer/products membership flag Field
     *
     * @return QUI\ERP\Products\Interfaces\FieldInterface|false
     */
    public static function getProductMembershipFlagField()
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return false;
        }

        try {
            return ProductFields::getField(self::PRODUCTS_FIELD_MEMBERSHIPFLAG);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: getProductMembershipFlagField()');
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Get the default membership
     *
     * @return Membership|false - Membership or false if none set
     */
    public static function getDefaultMembership()
    {
        $membershipId = self::getSetting('defaultMembershipId');

        if (empty($membershipId)) {
            return false;
        }

        return self::getInstance()->getChild((int)$membershipId);
    }
}
