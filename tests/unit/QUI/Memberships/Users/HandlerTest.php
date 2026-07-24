<?php

namespace QUI\Memberships\Users;

use PHPUnit\Framework\TestCase;
use QUI\Memberships\Handler as MembershipsHandler;

class HandlerTest extends TestCase
{
    public function testFactoryMetadataIncludesAllPersistedAttributes(): void
    {
        $Handler = Handler::getInstance();

        self::assertSame('quiqqer_memberships_users', $Handler->getDataBaseTableName());
        self::assertSame(MembershipUser::class, $Handler->getChildClass());
        self::assertSame([
            'membershipId',
            'userId',
            'addedDate',
            'beginDate',
            'endDate',
            'extendCounter',
            'archived',
            'history',
            'cancelDate',
            'cancelEndDate',
            'cancelled',
            'cancelStatus',
            'archiveReason',
            'archiveDate',
            'extraData',
            'productId',
            'contractId'
        ], $Handler->getChildAttributes());
    }

    public function testUnknownUserIdentifierRemainsAccessible(): void
    {
        self::assertSame(
            ['missing-phpunit-user'],
            Handler::getInstance()->getUserIdentifiers('missing-phpunit-user')
        );
    }

    public function testUnknownContractHasNoMembershipUser(): void
    {
        self::assertFalse(
            Handler::getInstance()->getMembershipUserByContractId(2147483647)
        );
    }

    public function testConfigurationModesMatchPackageSettings(): void
    {
        self::assertSame(
            MembershipsHandler::getConfig()->get('membershipusers', 'extendMode'),
            Handler::getExtendMode()
        );
        self::assertSame(
            MembershipsHandler::getConfig()->get('memberships', 'durationMode'),
            Handler::getDurationMode()
        );
        self::assertSame(
            MembershipsHandler::getConfig()->get('membershipusers', 'viewDataMode'),
            Handler::getSetting('viewDataMode')
        );
    }
}
