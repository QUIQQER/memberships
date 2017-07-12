<?php

namespace QUI\Memberships;

use QUI\CRUD\Factory;
use QUI\Utils\Grid;
use QUI;
use QUI\Permissions\Permission;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\Memberships\Products\MembershipField;

class Handler extends Factory
{
    const PERMISSION_CREATE     = 'quiqqer.memberships.create';
    const PERMISSION_EDIT       = 'quiqqer.memberships.edit';
    const PERMISSION_DELETE     = 'quiqqer.memberships.delete';
    const PERMISSION_FORCE_EDIT = 'quiqqer.memberships.force_edit';

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

        return parent::createChild($data);
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

        $whereOr = array();

        if (!empty($searchParams['search'])) {
            $search = $searchParams['search'];

            $whereOr['title'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );

            $whereOr['description'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );

            $whereOr['content'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );
        }

        if ($countOnly) {
            $result = QUI::getDataBase()->fetch(array(
                'count'    => 1,
                'from'     => $this->getDataBaseTableName(),
                'where_or' => $whereOr
            ));

            return current(current($result));
        }

        $result = QUI::getDataBase()->fetch(array(
            'select'   => array(
                'id'
            ),
            'from'     => $this->getDataBaseTableName(),
            'where_or' => $whereOr,
            'limit'    => $gridParams['limit']
        ));

        foreach ($result as $row) {
            $memberships[] = $row['id'];
        }

        return $memberships;
    }

    /**
     * Get list of all packages that are relevant for quiqqer/memberships
     * and that are currently installed
     *
     * @return array
     */
    public function getInstalledMembershipPackages()
    {
        $packages         = array();
        $relevantPackages = array(
            'quiqqer/products',
            'quiqqer/contracts'
        );

        foreach ($relevantPackages as $package) {
            try {
                QUI::getPackage($package);
                $packages[] = $package;
            } catch (\Exception $Exception) {
                // ignore (package is probably not installed)
            }
        }

        return $packages;
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
            QUI\System\Log::addError(self::class . ' :: getProductCategory()');
            QUI\System\Log::writeException($Exception);

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
    public static function getProductField()
    {
        try {
            return ProductFields::getField(MembershipField::FIELD_ID);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: getProductField()');
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }
}
