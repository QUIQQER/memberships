<?php

namespace QUI\Memberships\MCP;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Memberships\Membership;

require_once __DIR__ . '/TestableAbstractTool.php';

class AbstractToolTest extends TestCase
{
    protected function tearDown(): void
    {
        QUI\AI\MCP\Server::setRequestUser(null);
        parent::tearDown();
    }

    public function testParseTranslationsFiltersInvalidValues(): void
    {
        self::assertSame([], TestableAbstractTool::parseTranslationsPublic(null));
        self::assertSame([], TestableAbstractTool::parseTranslationsPublic(''));
        self::assertSame([], TestableAbstractTool::parseTranslationsPublic('42'));
        self::assertSame([], TestableAbstractTool::parseTranslationsPublic('{invalid'));
        self::assertSame(
            ['de' => 'Hallo', 'empty' => ''],
            TestableAbstractTool::parseTranslationsPublic(
                '{"de":"Hallo","number":42,"null":null,"empty":""}'
            )
        );
    }

    public function testSanitizeLimitUsesDefaultsAndBounds(): void
    {
        self::assertSame(20, TestableAbstractTool::sanitizeLimitPublic(null));
        self::assertSame(20, TestableAbstractTool::sanitizeLimitPublic(0));
        self::assertSame(1, TestableAbstractTool::sanitizeLimitPublic(-10));
        self::assertSame(50, TestableAbstractTool::sanitizeLimitPublic(50));
        self::assertSame(100, TestableAbstractTool::sanitizeLimitPublic(101));
    }

    public function testValidateGroupIdsNormalizesAndChecksGroups(): void
    {
        $RootGroup = QUI::getGroups()->get(QUI::conf('globals', 'root'));
        $groupId = (int)$RootGroup->getId();

        self::assertSame(
            [$groupId],
            TestableAbstractTool::validateGroupIdsPublic([$groupId, (string)$groupId, 0, -1])
        );
    }

    public function testValidateGroupIdsRejectsEmptyInput(): void
    {
        $this->expectException(QUI\Exception::class);
        $this->expectExceptionMessage('At least one valid group ID is required.');

        TestableAbstractTool::validateGroupIdsPublic([0, -1, 'invalid']);
    }

    public function testPermissionChecksAcceptSystemUser(): void
    {
        QUI\AI\MCP\Server::setRequestUser(QUI::getUsers()->getSystemUser());

        TestableAbstractTool::checkMembershipsPermissionPublic();
        TestableAbstractTool::checkEditPermissionPublic();

        self::assertTrue(true);
    }

    public function testParseMembershipIncludesDetailsAndGroupFallback(): void
    {
        $RootGroup = QUI::getGroups()->get(QUI::conf('globals', 'root'));
        $groupId = (int)$RootGroup->getId();
        $missingGroupId = 2147483647;
        $Membership = $this->getMockBuilder(Membership::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getTitle',
                'getDescription',
                'getContent',
                'getAttribute',
                'getGroupIds',
                'isAutoExtend',
                'getMembershipUserIds'
            ])
            ->getMock();

        $Membership->method('getId')->willReturn(17);
        $Membership->method('getTitle')->willReturn('Title');
        $Membership->method('getDescription')->willReturn('Description');
        $Membership->method('getContent')->willReturn('Content');
        $Membership->method('getGroupIds')->willReturn([$groupId, $missingGroupId]);
        $Membership->method('isAutoExtend')->willReturn(true);
        $Membership->method('getMembershipUserIds')->willReturn([3, 4]);
        $Membership->method('getAttribute')->willReturnMap([
            ['duration', '1-month'],
            ['paymentInterval', '1-month'],
            ['title', '{"de":"Titel","invalid":1}'],
            ['description', '{"de":"Beschreibung"}'],
            ['content', '{"de":"Inhalt"}'],
            ['createDate', '2024-01-01 00:00:00'],
            ['createUser', 1],
            ['editDate', null],
            ['editUser', null]
        ]);

        $summary = TestableAbstractTool::parseMembershipPublic($Membership);
        $details = TestableAbstractTool::parseMembershipPublic($Membership, true);

        self::assertSame(17, $summary['id']);
        self::assertSame(2, $summary['userCount']);
        self::assertArrayNotHasKey('content', $summary);
        self::assertSame('Content', $details['content']);
        self::assertSame(['de' => 'Titel'], $details['translations']['title']);
        self::assertSame($groupId, $details['groups'][0]['id']);
        self::assertSame($missingGroupId, $details['groups'][1]['id']);
        self::assertSame('', $details['groups'][1]['uuid']);
        self::assertSame('', $details['groups'][1]['name']);
    }
}
