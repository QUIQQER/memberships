<?php

namespace QUI\Memberships;

use DateTime;
use Doctrine\DBAL\Connection;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Contracts\Contract;
use QUI\ERP\Order\AbstractOrder;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Memberships\MCP\Provider;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;
use ReflectionProperty;
use Throwable;

class MembershipDbalIntegrationTest extends TestCase
{
    private const TEST_PREFIX = 'phpunit-memberships-dbal-';

    private ?UserInterface $previousSessionUser = null;

    public static function setUpBeforeClass(): void
    {
        self::skipIfDatabaseIsUnavailable();
        self::cleanupFixtures();
    }

    protected function setUp(): void
    {
        self::skipIfDatabaseIsUnavailable();
        self::cleanupFixtures();

        $this->previousSessionUser = self::replaceSessionUser(QUI::getUsers()->getSystemUser());
    }

    protected function tearDown(): void
    {
        Server::setRequestUser(null);
        self::cleanupFixtures();

        if ($this->previousSessionUser !== null) {
            self::replaceSessionUser($this->previousSessionUser);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::cleanupFixtures();
    }

    public function testMembershipSearchSupportsFiltersSortingPaginationAndGroupLookup(): void
    {
        $Group = $this->createTestGroup();
        $firstTitle = self::TEST_PREFIX . 'membership-a-' . uniqid();
        $secondTitle = self::TEST_PREFIX . 'membership-b-' . uniqid();
        $FirstMembership = $this->createMembership($firstTitle, $Group->getId());
        $SecondMembership = $this->createMembership($secondTitle, $Group->getId());
        $Handler = Handler::getInstance();

        $matchingIds = $Handler->search([
            'search' => self::TEST_PREFIX,
            'sortOn' => 'id',
            'sortBy' => 'DESC',
            'limit' => '0,1'
        ]);

        $this->assertSame([(int)$SecondMembership->getId()], $matchingIds);
        $this->assertSame(2, $Handler->search(['search' => self::TEST_PREFIX], true));

        $groupMembershipIds = $Handler->getMembershipIdsByGroupIds([$Group->getId()]);

        $this->assertContains((int)$FirstMembership->getId(), $groupMembershipIds);
        $this->assertContains((int)$SecondMembership->getId(), $groupMembershipIds);
        $this->assertSame([], $Handler->getMembershipIdsByGroupIds([]));
    }

    public function testMembershipUserAssignmentLookupArchivingAndDeletionLifecycle(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'lifecycle-' . uniqid(),
            $Group->getId()
        );
        $userId = $User->getId();

        if ($userId === false) {
            $this->fail('The test user has no database ID.');
        }

        $userUuid = (string)$User->getUUID();

        if ($userUuid === '') {
            $this->fail('The test user has no UUID.');
        }

        $CreatedMembershipUser = MembershipUsersHandler::getInstance()->createChild([
            'membershipId' => $Membership->getId(),
            'userId' => $userId
        ], $SystemUser);

        if (!$CreatedMembershipUser instanceof MembershipUser) {
            $this->fail('The membership user factory returned an unexpected object.');
        }

        $MembershipUser = $CreatedMembershipUser;
        $this->assertSame($userUuid, (string)$MembershipUser->getUserId());
        $this->assertTrue($User->isInGroup($Group->getUUID()));
        $this->assertTrue($Membership->hasMembershipUserId($userId));
        $this->assertTrue($Membership->hasMembershipUserId($userUuid));
        $this->assertSame(
            (int)$MembershipUser->getId(),
            (int)$Membership->getMembershipUser($userId)->getId()
        );
        $this->assertSame(
            (int)$MembershipUser->getId(),
            (int)$Membership->getMembershipUser($userUuid)->getId()
        );

        $MembershipUsers = MembershipUsersHandler::getInstance();
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            $MembershipUsers->getIdsByMembershipId((int)$Membership->getId())
        );
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            array_map(
                static fn (MembershipUser $Item): int => (int)$Item->getId(),
                $MembershipUsers->getMembershipUsersByUserId($userId)
            )
        );
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            array_map(
                static fn (MembershipUser $Item): int => (int)$Item->getId(),
                $MembershipUsers->getMembershipUsersByUserId($userUuid)
            )
        );
        $this->assertSame(
            [(int)$Membership->getId()],
            Handler::getInstance()->search(['userId' => $userId])
        );
        $this->assertSame(
            [(int)$Membership->getId()],
            Handler::getInstance()->search(['userId' => $userUuid])
        );

        $backendData = $MembershipUser->getBackendViewData();
        $frontendData = $MembershipUser->getFrontendViewData();

        self::assertSame($userUuid, (string)$backendData['userId']);
        self::assertSame((int)$Membership->getId(), (int)$backendData['membershipId']);
        self::assertSame($User->getUsername(), $backendData['username']);
        self::assertSame($userUuid, (string)$frontendData['userId']);
        self::assertSame((int)$Membership->getId(), (int)$frontendData['membershipId']);
        self::assertSame($User->getUsername(), $frontendData['username']);
        self::assertInstanceOf(DateTime::class, $MembershipUser->getCycleBeginDate());
        self::assertInstanceOf(DateTime::class, $MembershipUser->getCycleEndDate());
        self::assertInstanceOf(DateTime::class, $MembershipUser->getNextCycleBeginDate());
        self::assertInstanceOf(DateTime::class, $MembershipUser->getNextCycleEndDate());

        $MembershipUser->setExtraData('reference', 'first');
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();
        $MembershipUser->refresh();

        self::assertSame('first', $MembershipUser->getExtraData('reference'));

        $MembershipUser->setExtraData('reference', 'second');
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();
        $MembershipUser->refresh();

        self::assertSame('second', $MembershipUser->getExtraData('reference'));
        self::assertNotSame(
            '-',
            $MembershipUser->getExtraData()['reference']['edit']
        );

        $ExistingMembershipUser = $MembershipUsers->createChild([
            'membershipId' => $Membership->getId(),
            'userId' => $userUuid
        ], $SystemUser);

        $this->assertSame((int)$MembershipUser->getId(), (int)$ExistingMembershipUser->getId());
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            $MembershipUsers->getIdsByMembershipId((int)$Membership->getId())
        );

        $contractId = 900000000 + (int)$MembershipUser->getId();
        $productId = 800000000 + (int)$MembershipUser->getId();
        $MembershipUser->setAttributes([
            'contractId' => $contractId,
            'productId' => $productId
        ]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();

        $ContractMembershipUser = $MembershipUsers->getMembershipUserByContractId($contractId);

        if (!$ContractMembershipUser instanceof MembershipUser) {
            $this->fail('The membership user could not be loaded by contract ID.');
        }

        $this->assertSame((int)$MembershipUser->getId(), (int)$ContractMembershipUser->getId());
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            $Membership->searchUsers([
                'search' => self::TEST_PREFIX,
                'productId' => $productId,
                'sortOn' => 'username',
                'sortBy' => 'ASC'
            ])
        );
        $this->assertSame(
            1,
            $Membership->searchUsers([
                'search' => self::TEST_PREFIX,
                'productId' => $productId
            ], false, true)
        );

        $MembershipUser->delete();
        $MembershipUser->refresh();

        $this->assertTrue($MembershipUser->isArchived());
        $this->assertFalse($User->isInGroup($Group->getUUID()));
        $this->assertFalse($Membership->hasMembershipUserId($userId));
        $this->assertSame([], $MembershipUsers->getIdsByMembershipId((int)$Membership->getId()));
        $this->assertSame(
            [(int)$MembershipUser->getId()],
            $MembershipUsers->getIdsByMembershipId((int)$Membership->getId(), true)
        );

        $Membership->setEditUser($SystemUser);
        $Membership->delete();

        $this->assertFalse($this->membershipRowExists((int)$Membership->getId()));
    }

    public function testLegacyNumericUserIdsAreReadAndMigratedToUuids(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'migration-' . uniqid(),
            $Group->getId()
        );
        $userId = $User->getId();
        $userUuid = (string)$User->getUUID();

        if ($userId === false || $userUuid === '') {
            $this->fail('The test user does not provide both database ID and UUID.');
        }

        $CreatedMembershipUser = MembershipUsersHandler::getInstance()->createChild([
            'membershipId' => $Membership->getId(),
            'userId' => $userUuid
        ], $SystemUser);

        if (!$CreatedMembershipUser instanceof MembershipUser) {
            $this->fail('The membership user factory returned an unexpected object.');
        }

        $Connection = self::getConnection();
        $Connection->beginTransaction();

        try {
            $Connection->update(
                QUI\Utils\Doctrine::quoteIdentifier(
                    MembershipUsersHandler::getInstance()->getDataBaseTableName()
                ),
                ['userId' => $userId],
                ['id' => $CreatedMembershipUser->getId()]
            );

            $this->assertSame(
                (int)$CreatedMembershipUser->getId(),
                (int)$Membership->getMembershipUser($userUuid)->getId()
            );
            $this->assertTrue($Membership->hasMembershipUserId($userId));
            $this->assertTrue($Membership->hasMembershipUserId($userUuid));
            $this->assertSame(
                [(int)$CreatedMembershipUser->getId()],
                array_map(
                    static fn (MembershipUser $Item): int => (int)$Item->getId(),
                    MembershipUsersHandler::getInstance()->getMembershipUsersByUserId($userUuid)
                )
            );

            Events::migrateMembershipUserIdsToUuids();

            $this->assertSame(
                $userUuid,
                $this->getStoredMembershipUserIdentifier((int)$CreatedMembershipUser->getId())
            );
        } finally {
            $Connection->rollBack();
        }
    }

    public function testDefaultMembershipUserEventLifecycle(): void
    {
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'default-event-' . uniqid(),
            $Group->getId()
        );

        if (!$User instanceof QUI\Users\User) {
            $this->fail('The user event requires a concrete QUIQQER user.');
        }

        $Config = Handler::getConfig();
        $previousDefaultMembershipId = $Config->get('memberships', 'defaultMembershipId');
        $Config->set('memberships', 'defaultMembershipId', $Membership->getId());

        try {
            Events::onUserSave($User);
            Events::onUserSave($User);

            $membershipUserIds = $Membership->getMembershipUserIds();

            self::assertCount(1, $membershipUserIds);
            self::assertTrue($Membership->hasMembershipUserId((string)$User->getUUID()));
            self::assertTrue($User->isInGroup($Group->getUUID()));

            Events::onUserDelete($User);

            self::assertSame([], $Membership->getMembershipUserIds());
            self::assertSame($membershipUserIds, $Membership->getMembershipUserIds(true));
            self::assertFalse($User->isInGroup($Group->getUUID()));

            $ArchivedMembershipUser = MembershipUsersHandler::getInstance()->getChild(
                $membershipUserIds[0]
            );

            self::assertTrue($ArchivedMembershipUser->isArchived());
            self::assertSame(
                MembershipUsersHandler::ARCHIVE_REASON_DELETED,
                $ArchivedMembershipUser->getAttribute('archiveReason')
            );
        } finally {
            $Config->set(
                'memberships',
                'defaultMembershipId',
                $previousDefaultMembershipId
            );
        }
    }

    public function testMembershipEditingUniquenessDefaultAndLockLifecycle(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $FirstGroup = $this->createTestGroup();
        $SecondGroup = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'editing-' . uniqid(),
            $FirstGroup->getId()
        );
        $OtherMembership = $this->createMembership(
            self::TEST_PREFIX . 'overlap-' . uniqid(),
            $SecondGroup->getId()
        );
        $Membership->setAttributes([
            'groupIds' => $FirstGroup->getId() . ',' . $SecondGroup->getId(),
            'duration' => '2-week',
            'autoExtend' => 1
        ]);
        $Membership->setEditUser($SystemUser);
        $Membership->update();
        $Membership->refresh();

        self::assertSame(
            [(int)$FirstGroup->getId(), (int)$SecondGroup->getId()],
            $Membership->getGroupIds()
        );
        self::assertSame('2-week', $Membership->getAttribute('duration'));
        self::assertTrue($Membership->isAutoExtend());
        self::assertNotSame('', (string)$Membership->getAttribute('editDate'));
        self::assertSame(
            (string)$SystemUser->getId(),
            (string)$Membership->getAttribute('editUser')
        );
        self::assertSame(
            [(int)$FirstGroup->getId()],
            array_values($Membership->getUniqueGroupIds())
        );
        self::assertSame([], $Membership->getProducts());

        $Config = Handler::getConfig();
        $previousDurationMode = $Config->get('memberships', 'durationMode');
        $Config->set(
            'memberships',
            'durationMode',
            MembershipUsersHandler::DURATION_MODE_DAY
        );

        try {
            self::assertSame(
                '2024-01-15 23:59:59',
                $Membership->calcEndDate(strtotime('2024-01-01 10:00:00'))
            );
        } finally {
            $Config->set('memberships', 'durationMode', $previousDurationMode);
        }

        $previousDefaultMembershipId = $Config->get('memberships', 'defaultMembershipId');
        $Config->set('memberships', 'defaultMembershipId', $Membership->getId());

        try {
            self::assertTrue($Membership->isDefault());
            self::assertFalse($OtherMembership->isDefault());
        } finally {
            $Config->set(
                'memberships',
                'defaultMembershipId',
                $previousDefaultMembershipId
            );
        }

        self::assertFalse($Membership->isLocked());
        $Membership->lock();
        $Package = QUI::getPackage('quiqqer/memberships');
        $lockKey = 'membership_' . $Membership->getId();

        try {
            self::assertFalse($Membership->isLocked());
            self::assertSame(
                (string)$SystemUser->getUUID(),
                (string)QUI\Lock\Locker::isLocked(
                    $Package,
                    $lockKey,
                    $SystemUser,
                    false
                )
            );
        } finally {
            $Membership->unlock();
        }

        self::assertFalse($Membership->isLocked());
        self::assertFalse(
            QUI\Lock\Locker::isLocked(
                $Package,
                $lockKey,
                $SystemUser,
                false
            )
        );
        self::assertSame(
            [
                'id' => $Membership->getId(),
                'title' => $Membership->getTitle(),
                'description' => $Membership->getDescription(),
                'content' => $Membership->getContent()
            ],
            $Membership->getBackendViewData()
        );
    }

    public function testMembershipUpdateRejectsMissingGroups(): void
    {
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'invalid-groups-' . uniqid(),
            $Group->getId()
        );
        $Membership->setAttributes(['groupIds' => '']);
        $Membership->setEditUser(QUI::getUsers()->getSystemUser());

        $this->expectException(QUI\Memberships\Exception::class);
        $Membership->update();
    }

    public function testMembershipUpdateRejectsInvalidDuration(): void
    {
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'invalid-duration-' . uniqid(),
            $Group->getId()
        );
        $Membership->setAttributes([
            'groupIds' => (string)$Group->getId(),
            'duration' => '0-month'
        ]);
        $Membership->setEditUser(QUI::getUsers()->getSystemUser());

        $this->expectException(QUI\Memberships\Exception::class);
        $Membership->update();
    }

    public function testFactoriesRejectIncompleteMembershipData(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'factory-validation-' . uniqid(),
            $Group->getId()
        );
        $invalidCalls = [
            static fn() => Handler::getInstance()->createChild([
                'title' => '',
                'groupIds' => [$Group->getId()]
            ]),
            static fn() => Handler::getInstance()->createChild([
                'title' => self::TEST_PREFIX . 'missing-groups',
                'groupIds' => []
            ]),
            static fn() => MembershipUsersHandler::getInstance()->createChild([
                'membershipId' => $Membership->getId()
            ], $SystemUser),
            static fn() => MembershipUsersHandler::getInstance()->createChild([
                'userId' => $SystemUser->getUUID()
            ], $SystemUser)
        ];
        $exceptions = [];

        foreach ($invalidCalls as $invalidCall) {
            try {
                $invalidCall();
            } catch (QUI\Memberships\Exception $Exception) {
                $exceptions[] = $Exception;
            }
        }

        self::assertCount(4, $exceptions);
        self::assertSame(
            [],
            Handler::getInstance()->search([
                'search' => self::TEST_PREFIX . 'missing-groups'
            ])
        );
    }

    public function testMembershipUserUpdateRejectsInvalidFiniteDates(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'date-validation-' . uniqid(),
            $Group->getId()
        );
        $MembershipUser = $Membership->addUser($User);
        $invalidDates = [
            ['beginDate' => 'invalid', 'endDate' => '2024-02-01 00:00:00'],
            ['beginDate' => '2024-02-01 00:00:00', 'endDate' => '2024-02-01 00:00:00']
        ];
        $exceptions = [];

        foreach ($invalidDates as $dates) {
            $MembershipUser->setAttributes($dates);
            $MembershipUser->setEditUser($SystemUser);

            try {
                $MembershipUser->update();
            } catch (QUI\Memberships\Exception $Exception) {
                $exceptions[] = $Exception;
            }
        }

        self::assertCount(2, $exceptions);
    }

    public function testContractEventsExtendCancelAndUnlinkMembershipUser(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'contract-events-' . uniqid(),
            $Group->getId()
        );
        $MembershipUser = $Membership->addUser($User);
        $contractId = 700000000 + (int)$MembershipUser->getId();
        $MembershipUser->setAttributes(['contractId' => $contractId]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();

        $Config = Handler::getConfig();
        $previousLinkSetting = $Config->get('membershipusers', 'linkWithContracts');
        $previousMailSetting = $Config->get('membershipusers', 'sendAutoExtendMail');
        $Config->set('membershipusers', 'linkWithContracts', 1);
        $Config->set('membershipusers', 'sendAutoExtendMail', 0);
        $Contract = $this->getMockBuilder(Contract::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCleanId', 'getTerminationDate'])
            ->getMock();
        $Contract->method('getCleanId')->willReturn($contractId);
        $Contract->method('getTerminationDate')
            ->willReturn(new DateTime('2024-03-31 23:59:59'));

        try {
            self::assertTrue(Handler::isLinkedToContracts());
            self::assertSame($contractId, $MembershipUser->getContractId());
            self::assertInstanceOf(
                MembershipUser::class,
                MembershipUsersHandler::getInstance()->getMembershipUserByContractId(
                    $contractId
                )
            );

            Events::onQuiqqerContractsExtend(
                $Contract,
                new DateTime('2024-01-31 23:59:59'),
                new DateTime('2024-02-29 23:59:59')
            );
            $MembershipUser->refresh();

            self::assertSame(
                '2024-02-01 00:00:00',
                $MembershipUser->getAttribute('beginDate')
            );
            self::assertSame(
                '2024-02-29 23:59:59',
                $MembershipUser->getAttribute('endDate')
            );
            self::assertSame(1, (int)$MembershipUser->getAttribute('extendCounter'));

            Events::onQuiqqerContractsCancel($Contract);
            $MembershipUser->refresh();

            self::assertTrue($MembershipUser->isCancelled());
            self::assertSame(
                MembershipUsersHandler::CANCEL_STATUS_CANCELLED_BY_SYSTEM,
                (int)$MembershipUser->getAttribute('cancelStatus')
            );
            self::assertSame(
                '2024-03-31 23:59:59',
                $MembershipUser->getAttribute('endDate')
            );

            Events::onQuiqqerContractsDelete($Contract);
            $MembershipUser->refresh();

            self::assertFalse($MembershipUser->getContractId());
            self::assertContains(
                MembershipUsersHandler::HISTORY_TYPE_EXTENDED,
                array_column($MembershipUser->getHistory(), 'type')
            );
            self::assertContains(
                MembershipUsersHandler::HISTORY_TYPE_CANCEL_START_SYSTEM,
                array_column($MembershipUser->getHistory(), 'type')
            );
        } finally {
            $Config->set('membershipusers', 'linkWithContracts', $previousLinkSetting);
            $Config->set('membershipusers', 'sendAutoExtendMail', $previousMailSetting);
        }
    }

    public function testUnverifiedCancellationEventResetsPendingState(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'verification-event-' . uniqid(),
            $Group->getId()
        );
        $MembershipUser = $Membership->addUser($User);
        $MembershipUser->setAttributes([
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING,
            'cancelDate' => '2024-01-01 00:00:00',
            'cancelEndDate' => '2024-02-01 00:00:00'
        ]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();

        Events::onQuiqqerVerificationDeleteUnverified((int)$MembershipUser->getId());
        $MembershipUser->refresh();

        self::assertSame(
            MembershipUsersHandler::CANCEL_STATUS_NOT_CANCELLED,
            (int)$MembershipUser->getAttribute('cancelStatus')
        );
        self::assertFalse($MembershipUser->isCancelled());
        self::assertNull($MembershipUser->getAttribute('cancelDate'));
        self::assertNull($MembershipUser->getAttribute('cancelEndDate'));
        self::assertContains(
            MembershipUsersHandler::HISTORY_TYPE_CANCEL_ABORT_CONFIRM,
            array_column($MembershipUser->getHistory(), 'type')
        );
    }

    public function testProductDeleteEventUnlinksMembershipUsers(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'product-delete-' . uniqid(),
            $Group->getId()
        );
        $MembershipUser = $Membership->addUser($User);
        $productId = 600000000 + (int)$MembershipUser->getId();
        $MembershipUser->setAttributes(['productId' => $productId]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();
        $MembershipField = Handler::getProductMembershipField();

        self::assertNotFalse($MembershipField);

        $Product = $this->getMockBuilder(QUI\ERP\Products\Product\Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFieldValue', 'getId'])
            ->getMock();
        $Product->expects(self::once())
            ->method('getFieldValue')
            ->with($MembershipField->getId())
            ->willReturn((int)$Membership->getId());
        $Product->expects(self::once())
            ->method('getId')
            ->willReturn($productId);

        Events::onQuiqqerProductsProductDelete($Product);
        $MembershipUser->refresh();

        self::assertNull($MembershipUser->getAttribute('productId'));
        self::assertSame(
            [(int)$MembershipUser->getId()],
            $Membership->searchUsers([])
        );
    }

    public function testMembershipProductOrderAddsUserAndCanBeDeleted(): void
    {
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'product-order-' . uniqid(),
            $Group->getId()
        );
        $Product = $Membership->createProduct();

        self::assertNotFalse($Product);

        try {
            self::assertSame(
                [(int)$Product->getId()],
                array_map(
                    static fn(QUI\ERP\Products\Product\Product $AssignedProduct): int =>
                        (int)$AssignedProduct->getId(),
                    $Membership->getProducts()
                )
            );

            $Articles = new ArticleList();
            $Articles->addArticle(new Article([
                'id' => $Product->getId(),
                'title' => 'Membership test product',
                'quantity' => 1
            ]));
            $OrderCustomer = QUI\ERP\User::convertUserToErpUser($User);
            $Order = $this->getMockBuilder(AbstractOrder::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getCustomer', 'getArticles', 'getPrefixedId'])
                ->getMockForAbstractClass();
            $Order->method('getCustomer')->willReturn($OrderCustomer);
            $Order->method('getArticles')->willReturn($Articles);
            $Order->method('getPrefixedId')->willReturn('PHPUNIT-ORDER');

            Events::onQuiqqerOrderSuccessful($Order);

            $MembershipUser = $Membership->getMembershipUser((string)$User->getUUID());

            self::assertSame(
                (string)$User->getUUID(),
                (string)$MembershipUser->getUserId()
            );
            self::assertContains(
                'Order: PHPUNIT-ORDER',
                array_column($MembershipUser->getHistory(), 'msg')
            );
            self::assertTrue($User->isInGroup($Group->getUUID()));
        } finally {
            $Product->delete();
        }

        self::assertSame([], $Membership->getProducts());
    }

    public function testMembershipDeleteDetachesAndDeactivatesProduct(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'delete-product-' . uniqid(),
            $Group->getId()
        );
        $Product = $Membership->createProduct();
        $MembershipField = Handler::getProductMembershipField();

        self::assertNotFalse($Product);
        self::assertNotFalse($MembershipField);
        $productId = (int)$Product->getId();

        try {
            $Membership->setEditUser($SystemUser);
            $Membership->delete();
            $ReloadedProduct = QUI\ERP\Products\Handler\Products::getProduct($productId);

            self::assertFalse($this->membershipRowExists((int)$Membership->getId()));
            self::assertEmpty($Product->getFieldValue($MembershipField->getId()));
            self::assertFalse($Product->isActive());
            self::assertNotSame($Product, $ReloadedProduct);
            self::assertEmpty($ReloadedProduct->getFieldValue($MembershipField->getId()));
            self::assertFalse($ReloadedProduct->isActive());
        } finally {
            $Product->delete();
        }
    }

    public function testAutoExtendMembershipCreatesPlanProduct(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'plan-product-' . uniqid(),
            $Group->getId()
        );
        $Membership->setAttributes([
            'groupIds' => (string)$Group->getId(),
            'duration' => '1-month',
            'autoExtend' => 1
        ]);
        $Membership->setEditUser($SystemUser);
        $Membership->update();
        $Product = $Membership->createProduct();

        self::assertInstanceOf(QUI\ERP\Plans\PlanProduct::class, $Product);

        try {
            self::assertSame(
                '1-month',
                $Product->getField(
                    QUI\ERP\Plans\Handler::FIELD_DURATION
                )->getValue()
            );
        } finally {
            $Product->delete();
        }
    }

    public function testCronExtendsExpiredAutoExtendMembership(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'cron-' . uniqid(),
            $Group->getId()
        );
        $Membership->setAttributes([
            'groupIds' => (string)$Group->getId(),
            'duration' => '1-month',
            'autoExtend' => 1
        ]);
        $Membership->setEditUser($SystemUser);
        $Membership->update();
        $MembershipUser = $Membership->addUser($User);
        $MembershipUser->setAttributes([
            'beginDate' => '2023-01-01 00:00:00',
            'endDate' => '2023-02-01 23:59:59',
            'extendCounter' => 0
        ]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();

        $Config = Handler::getConfig();
        $previousLinkSetting = $Config->get('membershipusers', 'linkWithContracts');
        $previousMailSetting = $Config->get('membershipusers', 'sendAutoExtendMail');
        $Config->set('membershipusers', 'linkWithContracts', 0);
        $Config->set('membershipusers', 'sendAutoExtendMail', 0);
        $Connection = self::getConnection();
        $Connection->beginTransaction();

        try {
            $table = QUI\Utils\Doctrine::quoteIdentifier(
                MembershipUsersHandler::getInstance()->getDataBaseTableName()
            );
            $Connection->createQueryBuilder()
                ->update($table)
                ->set('archived', '1')
                ->where('id <> :membershipUserId')
                ->setParameter('membershipUserId', $MembershipUser->getId())
                ->executeStatement();

            $Membership->refresh();
            $MembershipUser->refresh();

            self::assertTrue($Membership->isAutoExtend());
            self::assertFalse($MembershipUser->isArchived());
            self::assertLessThan(
                time(),
                strtotime((string)$MembershipUser->getAttribute('endDate'))
            );
            self::assertSame(
                (string)$User->getUUID(),
                (string)$MembershipUser->getUserOrThrow()->getUUID()
            );

            Cron::checkMembershipUsers();
            $MembershipUser->refresh();

            self::assertSame(1, (int)$MembershipUser->getAttribute('extendCounter'));
            self::assertSame(
                '2023-02-02 00:00:00',
                $MembershipUser->getAttribute('beginDate')
            );
            self::assertGreaterThan(
                strtotime('2023-02-01 23:59:59'),
                strtotime((string)$MembershipUser->getAttribute('endDate'))
            );
            self::assertContains(
                MembershipUsersHandler::HISTORY_TYPE_EXTENDED,
                array_column($MembershipUser->getHistory(), 'type')
            );
        } finally {
            $Connection->rollBack();
            $Config->set('membershipusers', 'linkWithContracts', $previousLinkSetting);
            $Config->set('membershipusers', 'sendAutoExtendMail', $previousMailSetting);
        }
    }

    public function testCronArchivesExpiredMembershipWithoutSendingMail(): void
    {
        [$MembershipUser, $User] = $this->createExpiredMembershipUser(
            'cron-expired-',
            false
        );
        $this->removeUserEmail($User);

        $this->runCronWithIsolatedMembershipUser(
            $MembershipUser,
            static function (MembershipUser $ReloadedMembershipUser): void {
                self::assertTrue($ReloadedMembershipUser->isArchived());
                self::assertSame(
                    MembershipUsersHandler::ARCHIVE_REASON_EXPIRED,
                    $ReloadedMembershipUser->getAttribute('archiveReason')
                );
                self::assertContains(
                    MembershipUsersHandler::HISTORY_TYPE_EXPIRED,
                    array_column($ReloadedMembershipUser->getHistory(), 'type')
                );
            }
        );
    }

    public function testCronArchivesCancelledMembershipAfterCancellationEnd(): void
    {
        [$MembershipUser, $User] = $this->createExpiredMembershipUser(
            'cron-cancelled-',
            true
        );
        $this->removeUserEmail($User);

        $this->runCronWithIsolatedMembershipUser(
            $MembershipUser,
            static function (MembershipUser $ReloadedMembershipUser): void {
                self::assertTrue($ReloadedMembershipUser->isArchived());
                self::assertSame(
                    MembershipUsersHandler::ARCHIVE_REASON_CANCELLED,
                    $ReloadedMembershipUser->getAttribute('archiveReason')
                );
                self::assertContains(
                    MembershipUsersHandler::HISTORY_TYPE_ARCHIVED,
                    array_column($ReloadedMembershipUser->getHistory(), 'type')
                );
            }
        );
    }

    public function testMcpToolsReadUpdateAndValidateMemberships(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . 'mcp-' . uniqid(),
            $Group->getId()
        );
        Server::setRequestUser($SystemUser);
        $Builder = new Builder();
        (new Provider())->register($Builder);
        $tools = $Builder->getTools();
        $update = $tools['quiqqer_memberships_update']['callback'];
        $get = $tools['quiqqer_memberships_get']['callback'];
        $list = $tools['quiqqer_memberships_list']['callback'];
        $groups = $tools['quiqqer_memberships_groups_list']['callback'];
        $membershipId = (int)$Membership->getId();
        $updatedTitle = self::TEST_PREFIX . 'updated-' . uniqid();

        foreach (
            [
                [],
                ['groupIds' => 'invalid'],
                ['duration' => 5],
                ['duration' => 'invalid'],
                ['autoExtend' => 1],
                ['paymentInterval' => 1],
                ['title' => 'invalid'],
                ['title' => ['de' => 42]]
            ] as $invalidAttributes
        ) {
            self::assertInstanceOf(
                CallToolResult::class,
                $update($membershipId, $invalidAttributes)
            );
        }

        $updated = $update($membershipId, [
            'groupIds' => [$Group->getId(), $Group->getId()],
            'duration' => '2-month',
            'autoExtend' => true,
            'paymentInterval' => null,
            'title' => ['de' => $updatedTitle, 'en' => 'Updated'],
            'description' => ['de' => 'Beschreibung'],
            'content' => ['de' => 'Inhalt']
        ]);

        self::assertIsArray($updated);
        self::assertSame($membershipId, $updated['id']);
        self::assertSame('2-month', $updated['duration']);
        self::assertTrue($updated['autoExtend']);
        self::assertSame([$Group->getId()], $updated['groupIds']);
        self::assertSame($updatedTitle, $updated['translations']['title']['de']);
        self::assertSame('Updated', $updated['translations']['title']['en']);
        self::assertSame('Beschreibung', $updated['translations']['description']['de']);
        self::assertSame('Inhalt', $updated['translations']['content']['de']);

        $details = $get($membershipId);

        self::assertIsArray($details);
        self::assertSame($membershipId, $details['id']);
        self::assertInstanceOf(CallToolResult::class, $get(2147483647));

        $memberships = $list(
            $updatedTitle,
            null,
            200,
            -5,
            'invalid',
            'invalid'
        );

        self::assertSame(1, $memberships['total']);
        self::assertSame(100, $memberships['limit']);
        self::assertSame(0, $memberships['offset']);
        self::assertSame($membershipId, $memberships['memberships'][0]['id']);

        $groupResult = $groups(
            (string)$Group->getUUID(),
            200,
            -5
        );

        self::assertSame(1, $groupResult['total']);
        self::assertSame(100, $groupResult['limit']);
        self::assertSame(0, $groupResult['offset']);
        self::assertSame((int)$Group->getId(), $groupResult['groups'][0]['id']);
    }

    private function createTestGroup(): QUI\Groups\Group
    {
        $Groups = QUI::getGroups();
        $RootGroup = $Groups->get(QUI::conf('globals', 'root'));

        return $RootGroup->createChild(
            self::TEST_PREFIX . 'group-' . uniqid(),
            QUI::getUsers()->getSystemUser()
        );
    }

    private function createTestUser(): UserInterface
    {
        $username = self::TEST_PREFIX . 'user-' . uniqid();

        try {
            return QUI::getUsers()->createChildWithAttributes([
                'username' => $username,
                'email' => $username . '@example.invalid',
                'firstname' => self::TEST_PREFIX,
                'lastname' => 'Integration'
            ], QUI::getUsers()->getSystemUser());
        } catch (QUI\Users\Exception $Exception) {
            if (str_contains($Exception->getMessage(), 'super-user')) {
                self::markTestSkipped('QUIQQER database has no usable super-user fixture.');
            }

            throw $Exception;
        }
    }

    private function createMembership(string $title, int | string $groupId): Membership
    {
        $CreatedMembership = Handler::getInstance()->createChild([
            'title' => $title,
            'groupIds' => [$groupId]
        ]);

        if (!$CreatedMembership instanceof Membership) {
            $this->fail('The membership factory returned an unexpected object.');
        }

        return $CreatedMembership;
    }

    /**
     * @return array{MembershipUser, UserInterface}
     */
    private function createExpiredMembershipUser(
        string $titleSuffix,
        bool $cancelled
    ): array {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Group = $this->createTestGroup();
        $User = $this->createTestUser();
        $Membership = $this->createMembership(
            self::TEST_PREFIX . $titleSuffix . uniqid(),
            $Group->getId()
        );
        $MembershipUser = $Membership->addUser($User);
        $MembershipUser->setAttributes([
            'beginDate' => '2023-01-01 00:00:00',
            'endDate' => '2023-02-01 23:59:59',
            'cancelled' => $cancelled ? 1 : 0,
            'cancelStatus' => $cancelled
                ? MembershipUsersHandler::CANCEL_STATUS_CANCELLED
                : MembershipUsersHandler::CANCEL_STATUS_NOT_CANCELLED,
            'cancelEndDate' => $cancelled ? '2023-02-01 23:59:59' : null
        ]);
        $MembershipUser->setEditUser($SystemUser);
        $MembershipUser->update();

        return [$MembershipUser, $User];
    }

    private function removeUserEmail(UserInterface $User): void
    {
        $User->setAttribute('email', '');
        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * @param callable(MembershipUser): void $assertions
     */
    private function runCronWithIsolatedMembershipUser(
        MembershipUser $MembershipUser,
        callable $assertions
    ): void {
        $Connection = self::getConnection();
        $Connection->beginTransaction();

        try {
            $table = QUI\Utils\Doctrine::quoteIdentifier(
                MembershipUsersHandler::getInstance()->getDataBaseTableName()
            );
            $Connection->createQueryBuilder()
                ->update($table)
                ->set('archived', '1')
                ->where('id <> :membershipUserId')
                ->setParameter('membershipUserId', $MembershipUser->getId())
                ->executeStatement();

            Cron::checkMembershipUsers();
            $MembershipUser->refresh();
            $assertions($MembershipUser);
        } finally {
            $Connection->rollBack();
        }
    }

    private function membershipRowExists(int $membershipId): bool
    {
        $row = self::getConnection()->createQueryBuilder()
            ->select('id')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(Handler::getInstance()->getDataBaseTableName()))
            ->where('id = :id')
            ->setParameter('id', $membershipId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $row !== false;
    }

    private function getStoredMembershipUserIdentifier(int $membershipUserId): string
    {
        $userId = self::getConnection()->createQueryBuilder()
            ->select('userId')
            ->from(
                QUI\Utils\Doctrine::quoteIdentifier(
                    MembershipUsersHandler::getInstance()->getDataBaseTableName()
                )
            )
            ->where('id = :id')
            ->setParameter('id', $membershipUserId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return (string)$userId;
    }

    private static function skipIfDatabaseIsUnavailable(): void
    {
        try {
            $Connection = self::getConnection();

            foreach (
                [
                Handler::getInstance()->getDataBaseTableName(),
                MembershipUsersHandler::getInstance()->getDataBaseTableName()
                ] as $table
            ) {
                $Connection->createQueryBuilder()
                    ->select('1')
                    ->from(QUI\Utils\Doctrine::quoteIdentifier($table))
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->free();
            }
        } catch (Throwable $Exception) {
            self::markTestSkipped('QUIQQER database is not available: ' . $Exception->getMessage());
        }
    }

    private static function cleanupFixtures(): void
    {
        try {
            $Connection = self::getConnection();
            $Platform = $Connection->getDatabasePlatform();
            $membershipsTable = QUI\Utils\Doctrine::quoteIdentifier(
                Handler::getInstance()->getDataBaseTableName()
            );
            $membershipUsersTable = QUI\Utils\Doctrine::quoteIdentifier(
                MembershipUsersHandler::getInstance()->getDataBaseTableName()
            );
            $membershipIds = $Connection->createQueryBuilder()
                ->select('id')
                ->from($membershipsTable)
                ->where($Platform->quoteSingleIdentifier('title') . ' LIKE :title')
                ->setParameter('title', '%' . self::TEST_PREFIX . '%')
                ->executeQuery()
                ->fetchFirstColumn();

            foreach ($membershipIds as $membershipId) {
                $Connection->delete($membershipUsersTable, ['membershipId' => $membershipId]);
                $Connection->delete($membershipsTable, ['id' => $membershipId]);
            }

            self::cleanupTestUsers($Connection);
            self::cleanupTestGroups($Connection);
        } catch (Throwable) {
            // The availability check reports DB problems. Cleanup should not hide the test result.
        }
    }

    private static function cleanupTestUsers(Connection $Connection): void
    {
        $Platform = $Connection->getDatabasePlatform();
        $usersTable = QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table());
        $users = $Connection->createQueryBuilder()
            ->select('id', 'uuid')
            ->from($usersTable)
            ->where($Platform->quoteSingleIdentifier('username') . ' LIKE :username')
            ->setParameter('username', self::TEST_PREFIX . '%')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($users as $user) {
            foreach ([$user['id'], $user['uuid']] as $userIdentifier) {
                $Connection->delete(
                    QUI\Utils\Doctrine::quoteIdentifier(
                        MembershipUsersHandler::getInstance()->getDataBaseTableName()
                    ),
                    ['userId' => $userIdentifier]
                );
            }

            $Connection->delete(
                QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::tableAddress()),
                ['userUuid' => $user['uuid']]
            );
            $Connection->delete(
                QUI\Utils\Doctrine::quoteIdentifier(QUI\Workspace\Manager::table()),
                ['uid' => $user['uuid']]
            );
            $Connection->delete(
                QUI\Utils\Doctrine::quoteIdentifier(QUI\Workspace\Manager::table()),
                ['uid' => $user['id']]
            );
        }

        $Connection->createQueryBuilder()
            ->delete($usersTable)
            ->where($Platform->quoteSingleIdentifier('username') . ' LIKE :username')
            ->setParameter('username', self::TEST_PREFIX . '%')
            ->executeStatement();
    }

    private static function cleanupTestGroups(Connection $Connection): void
    {
        $Platform = $Connection->getDatabasePlatform();
        $groupsTable = QUI\Utils\Doctrine::quoteIdentifier(QUI\Groups\Manager::table());

        $Connection->createQueryBuilder()
            ->delete($groupsTable)
            ->where($Platform->quoteSingleIdentifier('name') . ' LIKE :name')
            ->setParameter('name', self::TEST_PREFIX . '%')
            ->executeStatement();
    }

    private static function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    private static function replaceSessionUser(UserInterface $User): ?UserInterface
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $Property->setAccessible(true);

        $PreviousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }
}
