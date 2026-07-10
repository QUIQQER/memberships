<?php

namespace QUI\Memberships;

use QUI;
use QUI\CRUD\Child;
use QUI\ERP\Plans\Handler as ErpPlansHandler;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\ERP\Products\Handler\Products as ProductsHandler;
use QUI\ERP\Products\Search\BackendSearch;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User as QUIUserInterface;
use QUI\Locale;
use QUI\Lock\Locker;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;
use QUI\Permissions\Exception;
use QUI\Permissions\Permission;

class Membership extends Child
{
    /**
     * User that is editing this Membership in this runtime
     *
     * @var ?QUIUserInterface
     */
    protected ?QUIUserInterface $EditUser = null;

    /**
     * Set User that is editing this Membership in this runtime
     *
     * @param QUIUserInterface $EditUser
     */
    public function setEditUser(QUIUserInterface $EditUser): void
    {
        $this->EditUser = $EditUser;
    }

    /**
     * Get IDs of all QUIQQER Groups
     *
     * @return int[]
     */
    public function getGroupIds(): array
    {
        $groupIds = $this->getAttribute('groupIds');
        $groupIds = trim($groupIds, ",");
        $groupIds = explode(",", $groupIds);

        return array_map('intval', $groupIds);
    }

    /**
     * Get membership title
     *
     * @param Locale|null $Locale (optional)
     * @return string - localized title
     */
    public function getTitle(null | Locale $Locale = null): string
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
     * @param Locale|null $Locale (optional)
     * @return string - localized description
     */
    public function getDescription(null | Locale $Locale = null): string
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
     * @param Locale|null $Locale (optional)
     * @return string - localized content
     */
    public function getContent(null | Locale $Locale = null): string
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
    public function isAutoExtend(): bool
    {
        return (bool)$this->getAttribute('autoExtend');
    }

    /**
     * @throws Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
     * @throws Exception
     */
    public function update(): void
    {
        Permission::checkPermission(Handler::PERMISSION_EDIT, $this->EditUser);

        $attributes = $this->getAttributes();

        // check groups
        if (empty($attributes['groupIds'])) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.handler.no.groups'
            ]);
        }

        $attributes['groupIds'] = ',' . $attributes['groupIds'] . ',';

        // check duration
        if (
            empty($attributes['duration'])
            || $attributes['duration'] === 'infinite'
        ) {
            $attributes['duration'] = 'infinite';
        } else {
            $duration = explode('-', $attributes['duration']);

            if ($duration[0] < 1) {
                throw new QUI\Memberships\Exception([
                    'quiqqer/memberships',
                    'exception.membership.update.duration.invalid'
                ]);
            }
        }

        // edit user and timestamp
        $attributes['editUser'] = QUI::getUserBySession()->getId();
        $attributes['editDate'] = Utils::getFormattedTimestamp();

        // autoExtend
        if (empty($attributes['autoExtend'])) {
            $attributes['autoExtend'] = 0;
        } else {
            $attributes['autoExtend'] = 1;
        }

        $this->setAttributes($attributes);

        parent::update();
    }

    /**
     * Delete membership
     *
     * Only possible if membership has no users in it!
     *
     * @return void
     * @throws QUI\Memberships\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function delete(): void
    {
        Permission::checkPermission(Handler::PERMISSION_DELETE, $this->EditUser);

        $MembershipUsers = MembershipUsersHandler::getInstance();

        if (count($MembershipUsers->getIdsByMembershipId((int)$this->id))) {
            throw new Exception([
                'quiqqer/memberships',
                'exception.membership.cannot.delete.with.users.left'
            ]);
        }

        if ($this->isDefault()) {
            $Conf = Handler::getConfig();
            $Conf->set('memberships', 'defaultMembershipId', '0');
            $Conf->save();
        }

        // remove from products
        if (Utils::isQuiqqerProductsInstalled() && class_exists('QUI\ERP\Products\Handler\Products')) {
            $MembershipFieldDefinition = Handler::getProductMembershipField();

            if ($MembershipFieldDefinition === false) {
                throw new QUI\Exception('Membership product field is not available.');
            }

            foreach ($this->getProducts() as $Product) {
                $MembershipField = $Product->getField(
                    $MembershipFieldDefinition->getId()
                );
                $MembershipField->setValue(null);

                $Product->deactivate();
                $Product->save();
            }
        }

        if (Utils::isQuiqqerContractsInstalled()) {
            // @todo quiqqer/contracts abhandeln
        }

        parent::delete();

        QUI::getEvents()->fireEvent('quiqqerMembershipsDelete', [$this->getId()]);
    }

    /**
     * Get a user of this membership (non-archived)
     *
     * @param int|string $userId - QUIQQER User ID
     * @return MembershipUser
     *
     * @throws Exception
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function getMembershipUser(int | string $userId): Users\MembershipUser
    {
        $QueryBuilder = QUI::getQueryBuilder();
        $membershipUserId = $QueryBuilder
            ->select('id')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(MembershipUsersHandler::getInstance()->getDataBaseTableName()))
            ->where($QueryBuilder->expr()->eq('membershipId', ':membershipId'))
            ->andWhere($QueryBuilder->expr()->eq('userId', ':userId'))
            ->andWhere($QueryBuilder->expr()->eq('archived', ':archived'))
            ->setParameter('membershipId', $this->id)
            ->setParameter('userId', $userId)
            ->setParameter('archived', 0)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($membershipUserId === false) {
            throw new Exception([
                'quiqqer/memberships',
                'exception.membership.user.not.found',
                [
                    'userId' => $userId
                ]
            ], 404);
        }

        return MembershipUsersHandler::getInstance()->getChild($membershipUserId);
    }

    /**
     * Get all membership user IDs
     *
     * @param bool $includeArchived (optional) - Include archived MembershipUsers
     * @return int[]
     */
    public function getMembershipUserIds(bool $includeArchived = false): array
    {
        $membershipUserIds = [];

        try {
            $QueryBuilder = QUI::getQueryBuilder();
            $QueryBuilder
                ->select('id')
                ->from(QUI\Utils\Doctrine::quoteIdentifier(MembershipUsersHandler::getInstance()->getDataBaseTableName()))
                ->where($QueryBuilder->expr()->eq('membershipId', ':membershipId'))
                ->setParameter('membershipId', $this->id);

            if (!$includeArchived) {
                $QueryBuilder
                    ->andWhere($QueryBuilder->expr()->eq('archived', ':archived'))
                    ->setParameter('archived', 0);
            }

            $result = $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $membershipUserIds;
        }

        foreach ($result as $row) {
            $membershipUserIds[] = $row['id'];
        }

        return $membershipUserIds;
    }

    /**
     * Get IDs of all QUIQQER Groups that are UNIQUE to this membership
     *
     * @return int[]
     * @throws QUI\Exception
     */
    public function getUniqueGroupIds(): array
    {
        $Memberships = Handler::getInstance();
        $groupIds = $this->getGroupIds();
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
     * @param int|string $userId
     * @return bool
     * @throws QUI\Database\Exception
     */
    public function hasMembershipUserId(int | string $userId): bool
    {
        $QueryBuilder = QUI::getQueryBuilder();
        $count = $QueryBuilder
            ->select('COUNT(id)')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(MembershipUsersHandler::getInstance()->getDataBaseTableName()))
            ->where($QueryBuilder->expr()->eq('membershipId', ':membershipId'))
            ->andWhere($QueryBuilder->expr()->eq('userId', ':userId'))
            ->andWhere($QueryBuilder->expr()->eq('archived', ':archived'))
            ->setParameter('membershipId', $this->id)
            ->setParameter('userId', $userId)
            ->setParameter('archived', 0)
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }

    /**
     * Search membership users
     *
     * @template T of bool
     * @param array<string, mixed> $searchParams
     * @param bool $archivedOnly (optional) - search archived users only [default: false]
     * @param T $countOnly (optional) - get count for search result only [default: false]
     * @return (T is true ? int : array<int, int>) - membership user IDs or count
     * @throws QUI\Exception
     */
    public function searchUsers(array $searchParams, bool $archivedOnly = false, bool $countOnly = false): array | int
    {
        $Grid = new QUI\Utils\Grid($searchParams);
        $gridParams = $Grid->parseDBParams($searchParams);
        $tbl = MembershipUsersHandler::getInstance()->getDataBaseTableName();
        $usersTbl = QUI::getDBTableName('users');
        $QueryBuilder = QUI::getQueryBuilder();
        $QueryBuilder
            ->select($countOnly ? 'COUNT(musers.id)' : 'musers.id')
            ->from(QUI\Utils\Doctrine::quoteIdentifier($tbl), 'musers')
            ->leftJoin(
                'musers',
                QUI\Utils\Doctrine::quoteIdentifier($usersTbl),
                'users',
                'musers.userId = users.id'
            )
            ->where($QueryBuilder->expr()->eq('musers.membershipId', ':membershipId'))
            ->andWhere($QueryBuilder->expr()->eq('musers.archived', ':archived'))
            ->setParameter('membershipId', $this->id)
            ->setParameter('archived', $archivedOnly ? 1 : 0);

        if (!empty($searchParams['search']) && is_string($searchParams['search'])) {
            $searchColumns = [
                'users.username',
                'users.firstname',
                'users.lastname'
            ];
            $searchExpressions = [];

            foreach ($searchColumns as $column) {
                $searchExpressions[] = $QueryBuilder->expr()->like($column, ':search');
            }

            $QueryBuilder
                ->andWhere($QueryBuilder->expr()->or(...$searchExpressions))
                ->setParameter('search', '%' . $searchParams['search'] . '%');
        }

        if (!empty($searchParams['productId'])) {
            $QueryBuilder
                ->andWhere($QueryBuilder->expr()->eq('musers.productId', ':productId'))
                ->setParameter('productId', (int)$searchParams['productId']);
        }

        if (!$countOnly && !empty($searchParams['sortOn']) && is_string($searchParams['sortOn'])) {
            $sortOn = $searchParams['sortOn'];
            $userColumns = ['username', 'firstname', 'lastname'];
            $membershipUserColumns = array_merge(
                ['id'],
                MembershipUsersHandler::getInstance()->getChildAttributes()
            );

            if (in_array($sortOn, $userColumns, true) || in_array($sortOn, $membershipUserColumns, true)) {
                $sortDirection = !empty($searchParams['sortBy']) && is_string($searchParams['sortBy'])
                    ? strtoupper($searchParams['sortBy'])
                    : 'ASC';

                if (!in_array($sortDirection, ['ASC', 'DESC'], true)) {
                    $sortDirection = 'ASC';
                }

                $Platform = QUI::getDataBaseConnection()->getDatabasePlatform();
                $sortAlias = in_array($sortOn, $userColumns, true) ? 'users' : 'musers';
                $QueryBuilder->orderBy(
                    $sortAlias . '.' . $Platform->quoteSingleIdentifier($sortOn),
                    $sortDirection
                );
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
                self::class . ' :: searchUsers() -> ' . $Exception->getMessage()
            );

            return $countOnly ? 0 : [];
        }

        if ($countOnly) {
            return (int)$Result->fetchOne();
        }

        return array_map('intval', $Result->fetchFirstColumn());
    }

    /**
     * Calculate the end date for this membership based on a given time
     *
     * @param int|null $start (optional) - UNIX timestamp; if omitted use time()
     * @return string|null - formatted timestamp
     */
    public function calcEndDate(null | int $start = null): ?string
    {
        if ($this->isInfinite()) {
            return null;
        }

        if (is_null($start)) {
            $start = time();
        }

        $start = Utils::getFormattedTimestamp($start);

        $duration = explode('-', $this->getAttribute('duration'));
        $durationCount = $duration[0];
        $durationScope = $duration[1];

        $durationMode = Handler::getSetting('durationMode');

        switch ($durationMode) {
            case MembershipUsersHandler::DURATION_MODE_DAY:
                $endTime = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);

                if ($endTime === false) {
                    throw new \UnexpectedValueException('Could not calculate membership end date.');
                }

                $beginOfDay = strtotime("midnight", $endTime);
                $end = strtotime("tomorrow", $beginOfDay) - 1;
                break;

            default:
                $end = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);

                if ($end === false) {
                    throw new \UnexpectedValueException('Could not calculate membership end date.');
                }
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
    public function getProducts(): array
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return [];
        }

        if (
            !class_exists('QUI\ERP\Products\Search\BackendSearch')
            || !class_exists('QUI\ERP\Products\Handler\Products')
            || !class_exists('QUI\ERP\Products\Field\Field')
        ) {
            return [];
        }

        try {
            $Search = new BackendSearch();
            $MembershipField = Handler::getProductMembershipField();

            if ($MembershipField === false) {
                return [];
            }

            $result = $Search->search([
                'fields' => [
                    $MembershipField->getId() => "$this->id" // has to be string
                ]
            ]);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        $products = [];

        if (!is_array($result)) {
            return [];
        }

        foreach ($result as $id) {
            try {
                $products[] = ProductsHandler::getProduct((int)$id);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
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
     * @throws QUI\Exception
     */
    public function createProduct(): QUI\ERP\Products\Product\Product | bool
    {
        if (!Utils::isQuiqqerProductsInstalled()) {
            return false;
        }

        if (
            !class_exists('QUI\ERP\Products\Handler\Products')
            || !class_exists('QUI\ERP\Products\Handler\Fields')
            || !class_exists('QUI\ERP\Products\Field\Field')
        ) {
            return false;
        }

        $categories = [];
        $fields = [];

        $Category = Handler::getProductCategory();

        if ($Category) {
            $categories[] = $Category;
        }

        $MembershipField = Handler::getProductMembershipField();

        if ($MembershipField !== false) {
            $MembershipField->setOwnFieldStatus(true);
            $MembershipField->setValue($this->id);
            $fields[] = $MembershipField;
        }

        $MembershipFlagField = Handler::getProductMembershipFlagField();

        if ($MembershipFlagField !== false) {
            $MembershipFlagField->setOwnFieldStatus(true);
            $MembershipFlagField->setValue(true);
            $fields[] = $MembershipFlagField;
        }

        // set title and description
        $TitleField = ProductFields::getField(ProductFields::FIELD_TITLE);
        $DescField = ProductFields::getField(ProductFields::FIELD_SHORT_DESC);
        $title = json_decode($this->getAttribute('title'), true);
        $description = json_decode($this->getAttribute('description'), true);

        if (!empty($title)) {
            $TitleField->setValue($title);
            $fields[] = $TitleField;
        }

        if (!empty($description)) {
            $DescField->setValue($description);
            $fields[] = $DescField;
        }

        if (
            $this->isAutoExtend()
            && Utils::isQuiqqerErpPlansInstalled()
            && class_exists('QUI\ERP\Plans\Handler')
            && class_exists('QUI\ERP\Plans\PlanProduct')
        ) {
            $Product = ProductsHandler::createProduct($categories, $fields, QUI\ERP\Plans\PlanProduct::class);

            $Product->getField(ErpPlansHandler::FIELD_DURATION)->setValue($this->getAttribute('duration'));
            $Product->getField(ErpPlansHandler::FIELD_AUTO_EXTEND)->setValue(true);
            $Product->getField(ErpPlansHandler::FIELD_INVOICE_INTERVAL)->setValue($this->getAttribute('duration'));
            $Product->getField(ErpPlansHandler::FIELD_MIN_DURATION)->setValue($this->getAttribute('duration'));
        } else {
            $Product = ProductsHandler::createProduct($categories, $fields);
        }

        if (!empty($categories)) {
            $Product->setMainCategory($categories[0]);
        }

        $Product->save(QUI::getUsers()->getSystemUser());

        QUI::getEvents()->fireEvent('quiqqerMembershipsCreateProduct', [$this, $Product]);

        return $Product;
    }

    /**
     * Locks editing of this membership for the current session user
     *
     * @return void
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function lock(): void
    {
        Locker::lock(QUI::getPackage('quiqqer/memberships'), $this->getLockKey());
    }

    /**
     * Unlock membership (requires permission!)
     *
     * @return void
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function unlock(): void
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
     * @throws QUI\Exception
     */
    public function isLocked(): bool
    {
        return Locker::isLocked(QUI::getPackage('quiqqer/memberships'), $this->getLockKey());
    }

    /**
     * Get membership lock key
     *
     * @return string
     */
    protected function getLockKey(): string
    {
        return 'membership_' . $this->id;
    }

    /**
     * Get membership data for backend view/edit purposes
     *
     * @return array<string, mixed>
     */
    public function getBackendViewData(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'content' => $this->getContent()
        ];
    }

    /**
     * Check if this membership has an infinite duration (never expires)
     *
     * @return bool
     */
    public function isInfinite(): bool
    {
        return $this->getAttribute('duration') === 'infinite';
    }

    /**
     * Check if this Membership is the default Memberships
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        $DefaultMembership = Handler::getDefaultMembership();

        if ($DefaultMembership === false) {
            return false;
        }

        return $DefaultMembership->getId() === $this->getId();
    }

    /**
     * Add user to the membership
     *
     * @param QUIUserInterface $User
     * @return QUI\Memberships\Users\MembershipUser
     * @throws QUI\Exception
     */
    public function addUser(QUIUserInterface $User): QUI\Memberships\Users\MembershipUser
    {
        $MembershipUser = MembershipUsersHandler::getInstance()->createChild([
            'userId' => $User->getId(),
            'membershipId' => $this->id
        ], $this->EditUser);

        /* @var $MembershipUser QUI\Memberships\Users\MembershipUser */
        // @phpstan-ignore-next-line
        return $MembershipUser;
    }
}
