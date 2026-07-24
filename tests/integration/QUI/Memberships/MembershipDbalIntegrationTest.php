<?php

namespace QUI\Memberships;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Interfaces\Users\User as UserInterface;
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
