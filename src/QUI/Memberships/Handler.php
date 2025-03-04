<?php

namespace QUI\Memberships;

use PDO;
use QUI;
use QUI\CRUD\Factory;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\Exception;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Permissions\Permission;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

class Handler extends Factory
{
    /**
     * quiqqer/memberships permissions
     */
    const PERMISSION_CREATE = 'quiqqer.memberships.create';
    const PERMISSION_EDIT = 'quiqqer.memberships.edit';
    const PERMISSION_DELETE = 'quiqqer.memberships.delete';
    const PERMISSION_FORCE_EDIT = 'quiqqer.memberships.force_edit';

    /**
     * @inheritdoc
     * @throws QUI\Memberships\Exception
     */
    public function createChild($data = []): QUI\CRUD\Child
    {
        Permission::checkPermission(self::PERMISSION_CREATE);

        $data['createDate'] = Utils::getFormattedTimestamp();
        $data['createUser'] = QUI::getUserBySession()->getId();

        // title
        $title = trim($data['title']);

        if (empty($title)) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.handler.no.title'
            ]);
        }

        $data['title'] = [];

        foreach (QUI::availableLanguages() as $lang) {
            $data['title'][$lang] = $title;
        }

        $data['title'] = json_encode($data['title']);

        // groupIds
        $Groups = QUI::getGroups();
        $groupIds = $data['groupIds'];

        if (
            empty($groupIds)
            || !is_array($groupIds)
        ) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.handler.no.groups'
            ]);
        }

        foreach ($groupIds as $groupId) {
            // check if group exist by getting them
            $Groups->get($groupId);
        }

        $data['groupIds'] = ',' . implode(',', $groupIds) . ',';
        $data['duration'] = '1-month';
        $data['autoExtend'] = 0;
        $data['editDate'] = null;
        $data['editUser'] = null;

        /** @var Membership $NewMembership */
        $NewMembership = parent::createChild($data);

        QUI::getEvents()->fireEvent('quiqqerMembershipsCreate', [$NewMembership]);

        return $NewMembership;
    }

    /**
     * @return string
     */
    public function getDataBaseTableName(): string
    {
        return 'quiqqer_memberships';
    }

    /**
     * @return string
     */
    public function getChildClass(): string
    {
        return Membership::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes(): array
    {
        return [
            'title',
            'description',
            'content',
            'duration',
            'groupIds',
            'autoExtend',
            'editDate',
            'editUser',
            'createDate',
            'createUser',

            // these fields require quiqqer/order
            'paymentInterval'

            // @todo additional fields for quiqqer/contracts
        ];
    }

    /**
     * Search memberships
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return array|int - membership IDs
     * @throws Exception
     */
    public function search(array $searchParams, bool $countOnly = false): array | int
    {
        $memberships = [];
        $Grid = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);

        $binds = [];
        $where = [];

        if ($countOnly) {
            $sql = "SELECT COUNT(*)";
        } else {
            $sql = "SELECT id";
        }

        $sql .= " FROM `" . $this->getDataBaseTableName() . "`";

        if (!empty($searchParams['userId'])) {
            $memberhsipUsers = MembershipUsersHandler::getInstance()->getMembershipUsersByUserId(
                $searchParams['userId']
            );

            $membershipIds = [];

            foreach ($memberhsipUsers as $MembershipUser) {
                $membershipIds[] = $MembershipUser->getMembership()->getId();
            }

            if (!empty($membershipIds)) {
                $where[] = '`id` IN (' . implode(',', $membershipIds) . ')';
            }
        }

        if (!empty($searchParams['search'])) {
            $searchColumns = [
                'title',
                'description',
                'content'
            ];

            $whereOr = [];

            foreach ($searchColumns as $searchColumn) {
                $whereOr[] = '`' . $searchColumn . '` LIKE :search';
            }

            if (!empty($whereOr)) {
                $where[] = '(' . implode(' OR ', $whereOr) . ')';

                $binds['search'] = [
                    'value' => '%' . $searchParams['search'] . '%',
                    'type' => PDO::PARAM_STR
                ];
            }
        }

        // build WHERE query string
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        // ORDER
        if (!empty($searchParams['sortOn'])) {
            $sortOn = Orthos::clear($searchParams['sortOn']);
            $order = "ORDER BY " . $sortOn;

            if (!empty($searchParams['sortBy'])) {
                $order .= " " . Orthos::clear($searchParams['sortBy']);
            } else {
                $order .= " ASC";
            }

            $sql .= " " . $order;
        }

        // LIMIT
        if (!empty($gridParams['limit']) && !$countOnly) {
            $sql .= " LIMIT " . $gridParams['limit'];
        } else {
            if (!$countOnly) {
                $sql .= " LIMIT " . 20;
            }
        }

        $Stmt = QUI::getPDO()->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: searchUsers() -> ' . $Exception->getMessage()
            );

            return [];
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
    public function getMembershipIdsByGroupIds(array $groupIds): array
    {
        $ids = [];

        if (empty($groupIds)) {
            return $ids;
        }

        $sql = 'SELECT `id` FROM ' . self::getDataBaseTableName();
        $sql .= ' WHERE ';

        $whereOr = [];
        $binds = [];

        foreach ($groupIds as $groupId) {
            $bindParam = md5($groupId);
            $whereOr[] = '`groupIds` LIKE :' . $bindParam;
            $binds[$bindParam] = [
                'value' => '%,' . $groupId . ',%',
                'type' => PDO::PARAM_STR
            ];
        }

        $sql .= implode(" OR ", $whereOr);

        $PDO = QUI::getDataBase()->getPDO();
        $Stmt = $PDO->prepare($sql);

        // bind search values
        foreach ($binds as $var => $bind) {
            $Stmt->bindValue(':' . $var, $bind['value'], $bind['type']);
        }

        try {
            $Stmt->execute();
            $result = $Stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
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
    public static function getSetting(string $key): mixed
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
    public static function getProductCategory(): bool | QUI\ERP\Products\Interfaces\CategoryInterface
    {
        $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();
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
     * @return QUI\ERP\Products\Field\Field|false
     */
    public static function getProductMembershipField(): bool | QUI\ERP\Products\Field\Field
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return false;
        }

        try {
            $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();
            $fieldId = $Conf->get('products', 'membershipFieldId');

            if (empty($fieldId)) {
                return false;
            }

            return ProductFields::getField($fieldId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

    /**
     * Require: quiqqer/products
     *
     * Get quiqqer/products membership flag Field
     *
     * @return QUI\ERP\Products\Field\Field|false
     */
    public static function getProductMembershipFlagField(): bool | QUI\ERP\Products\Field\Field
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return false;
        }

        try {
            $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();
            $fieldId = $Conf->get('products', 'membershipFlagFieldId');

            if (empty($fieldId)) {
                return false;
            }

            return ProductFields::getField($fieldId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

    /**
     * Get the default membership
     *
     * @return Membership|false - Membership or false if none set
     */
    public static function getDefaultMembership(): Membership | bool
    {
        $membershipId = self::getSetting('defaultMembershipId');

        if (empty($membershipId)) {
            return false;
        }

        /* @var $Membership Membership */
        $Membership = self::getInstance()->getChild((int)$membershipId);

        return $Membership;
    }

    /**
     * Check if memberships are linked to contracts
     *
     * @return bool
     */
    public static function isLinkedToContracts(): bool
    {
        try {
            $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();

            if ((int)$Conf->get('membershipusers', 'linkWithContracts')) {
                return true;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        return false;
    }
}
