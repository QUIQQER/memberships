<?php

namespace QUI\Memberships;

use QUI;
use QUI\CRUD\Factory;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\Exception;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Permissions\Permission;
use QUI\Utils\Grid;

class Handler extends Factory
{
    /**
     * quiqqer/memberships permissions
     */
    const PERMISSION_CREATE = 'quiqqer.memberships.create';
    const PERMISSION_EDIT = 'quiqqer.memberships.edit';
    const PERMISSION_DELETE = 'quiqqer.memberships.delete';
    const PERMISSION_FORCE_EDIT = 'quiqqer.memberships.force_edit';

    public function getChild(int | string $id): Membership
    {
        /* @var $Membership Membership */
        $Membership = parent::getChild($id);

        // @phpstan-ignore-next-line
        return $Membership;
    }

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
     * @throws QUI\Exception
     */
    public static function getConfig(): QUI\Config
    {
        $Config = QUI::getPackage('quiqqer/memberships')->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('Memberships configuration is not available.');
        }

        return $Config;
    }

    /**
     * @return string
     */
    public function getChildClass(): string
    {
        return Membership::class;
    }

    /**
     * @return array<int, string>
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
     * @template T of bool
     * @param array<string, mixed> $searchParams
     * @param T $countOnly (optional) - get count for search result only [default: false]
     * @return (T is true ? int : array<int, int>) - membership IDs
     * @throws Exception
     */
    public function search(array $searchParams, bool $countOnly = false): array | int
    {
        $Grid = new Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);
        $QueryBuilder = QUI::getQueryBuilder();
        $QueryBuilder
            ->select($countOnly ? 'COUNT(id)' : 'id')
            ->from(QUI\Utils\Doctrine::quoteIdentifier($this->getDataBaseTableName()));

        if (!empty($searchParams['userId'])) {
            $membershipUsers = MembershipUsersHandler::getInstance()->getMembershipUsersByUserId(
                $searchParams['userId']
            );
            $membershipIds = [];

            foreach ($membershipUsers as $MembershipUser) {
                $membershipIds[] = $MembershipUser->getMembership()->getId();
            }

            if (empty($membershipIds)) {
                return $countOnly ? 0 : [];
            }

            $membershipPlaceholders = [];

            foreach ($membershipIds as $index => $membershipId) {
                $parameter = 'membershipId' . $index;
                $membershipPlaceholders[] = ':' . $parameter;
                $QueryBuilder->setParameter($parameter, $membershipId);
            }

            $QueryBuilder->andWhere($QueryBuilder->expr()->in('id', $membershipPlaceholders));
        }

        if (!empty($searchParams['search']) && is_string($searchParams['search'])) {
            $searchColumns = [
                'title',
                'description',
                'content'
            ];
            $searchExpressions = [];

            foreach ($searchColumns as $searchColumn) {
                $searchExpressions[] = $QueryBuilder->expr()->like($searchColumn, ':search');
            }

            $QueryBuilder
                ->andWhere($QueryBuilder->expr()->or(...$searchExpressions))
                ->setParameter('search', '%' . $searchParams['search'] . '%');
        }

        if (!$countOnly && !empty($searchParams['sortOn']) && is_string($searchParams['sortOn'])) {
            $sortOn = $searchParams['sortOn'];
            $sortableColumns = array_merge(['id'], $this->getChildAttributes());

            if (in_array($sortOn, $sortableColumns, true)) {
                $sortDirection = !empty($searchParams['sortBy']) && is_string($searchParams['sortBy'])
                    ? strtoupper($searchParams['sortBy'])
                    : 'ASC';

                if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
                    $sortDirection = 'ASC';
                }

                $Platform = QUI::getDataBaseConnection()->getDatabasePlatform();
                $QueryBuilder->orderBy($Platform->quoteSingleIdentifier($sortOn), $sortDirection);
            }
        }

        if (!$countOnly) {
            if (!empty($gridParams['limit'])) {
                $limit = explode(',', (string)$gridParams['limit'], 2);

                if (isset($limit[1])) {
                    $QueryBuilder->setFirstResult((int)$limit[0]);
                    $QueryBuilder->setMaxResults((int)$limit[1]);
                } else {
                    $QueryBuilder->setMaxResults((int)$limit[0]);
                }
            } else {
                $QueryBuilder->setMaxResults(20);
            }
        }

        try {
            $Result = $QueryBuilder->executeQuery();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: search() -> ' . $Exception->getMessage()
            );

            return $countOnly ? 0 : [];
        }

        if ($countOnly) {
            return (int)$Result->fetchOne();
        }

        return array_map('intval', $Result->fetchFirstColumn());
    }

    /**
     * Get IDs of all memberships that have specific groups assigned (OR)
     *
     * @param array<int, int|string> $groupIds
     * @return int[]
     */
    public function getMembershipIdsByGroupIds(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        $QueryBuilder = QUI::getQueryBuilder();
        $QueryBuilder
            ->select('id')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(self::getDataBaseTableName()));
        $groupExpressions = [];

        foreach ($groupIds as $index => $groupId) {
            $parameter = 'groupId' . $index;
            $groupExpressions[] = $QueryBuilder->expr()->like('groupIds', ':' . $parameter);
            $QueryBuilder->setParameter($parameter, '%,' . $groupId . ',%');
        }

        $QueryBuilder->where($QueryBuilder->expr()->or(...$groupExpressions));

        try {
            $ids = $QueryBuilder->executeQuery()->fetchFirstColumn();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * Get config entry for a membership setting
     *
     * @param string $key
     * @return mixed
     */
    public static function getSetting(string $key): mixed
    {
        return self::getConfig()->get('memberships', $key);
    }

    /**
     * Requires: quiqqer/products
     *
     * Get Memberships product category
     *
     * @return QUI\ERP\Products\Interfaces\CategoryInterface|false
     * @throws Exception
     */
    public static function getProductCategory(): bool | QUI\ERP\Products\Interfaces\CategoryInterface
    {
        $categoryId = self::getConfig()->get('products', 'categoryId');

        if (empty($categoryId)) {
            return false;
        }

        if (!class_exists('QUI\ERP\Products\Handler\Categories')) {
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

        if (!class_exists('QUI\ERP\Products\Handler\Fields')) {
            return false;
        }

        try {
            $fieldId = self::getConfig()->get('products', 'membershipFieldId');

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

        if (!class_exists('QUI\ERP\Products\Handler\Fields')) {
            return false;
        }

        try {
            $fieldId = self::getConfig()->get('products', 'membershipFlagFieldId');

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
     * @throws Exception
     */
    public static function getDefaultMembership(): Membership | bool
    {
        $membershipId = self::getSetting('defaultMembershipId');

        if (empty($membershipId)) {
            return false;
        }

        return self::getInstance()->getChild((int)$membershipId);
    }

    /**
     * Check if memberships are linked to contracts
     *
     * @return bool
     */
    public static function isLinkedToContracts(): bool
    {
        try {
            if ((int)self::getConfig()->get('membershipusers', 'linkWithContracts')) {
                return true;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        return false;
    }
}
