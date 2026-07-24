<?php

namespace QUI\Memberships;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Locale;

class MembershipTest extends TestCase
{
    public function testLocalizedAttributesAndSimpleFlags(): void
    {
        $Locale = new Locale();
        $Locale->setCurrent('de');
        $Membership = $this->createMembership([
            'groupIds' => ',2,7,',
            'title' => '{"de":"Titel","en":"Title"}',
            'description' => '{"de":"Kurz","en":"Short"}',
            'content' => '{"de":"Inhalt","en":"Content"}',
            'autoExtend' => 1,
            'duration' => 'infinite'
        ]);

        self::assertSame([2, 7], $Membership->getGroupIds());
        self::assertSame('Titel', $Membership->getTitle($Locale));
        self::assertSame('Kurz', $Membership->getDescription($Locale));
        self::assertSame('Inhalt', $Membership->getContent($Locale));
        self::assertTrue($Membership->isAutoExtend());
        self::assertTrue($Membership->isInfinite());
    }

    public function testLocalizedAttributesReturnEmptyStringForMissingLanguage(): void
    {
        $Locale = new Locale();
        $Locale->setCurrent('fr');
        $Membership = $this->createMembership([
            'title' => '{"de":"Titel"}',
            'description' => '{"de":"Kurz"}',
            'content' => '{"de":"Inhalt"}'
        ]);

        self::assertSame('', $Membership->getTitle($Locale));
        self::assertSame('', $Membership->getDescription($Locale));
        self::assertSame('', $Membership->getContent($Locale));
    }

    public function testBackendViewDataUsesLocalizedValues(): void
    {
        $Membership = $this->getMockBuilder(Membership::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getTitle', 'getDescription', 'getContent'])
            ->getMock();

        $Membership->method('getId')->willReturn(12);
        $Membership->method('getTitle')->willReturn('Title');
        $Membership->method('getDescription')->willReturn('Description');
        $Membership->method('getContent')->willReturn('Content');

        self::assertSame([
            'id' => 12,
            'title' => 'Title',
            'description' => 'Description',
            'content' => 'Content'
        ], $Membership->getBackendViewData());
    }

    public function testInfiniteMembershipHasNoCalculatedEndDate(): void
    {
        $Membership = $this->getMockBuilder(Membership::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isInfinite'])
            ->getMock();
        $Membership->method('isInfinite')->willReturn(true);

        self::assertNull($Membership->calcEndDate(1704067200));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createMembership(array $attributes): Membership
    {
        $Membership = $this->getMockBuilder(Membership::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();
        $Membership->method('getAttribute')->willReturnCallback(
            static fn(string $attribute): mixed => $attributes[$attribute] ?? null
        );

        return $Membership;
    }
}
