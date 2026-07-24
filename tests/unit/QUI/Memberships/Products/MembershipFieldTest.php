<?php

namespace QUI\Memberships\Products;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Products\Field\Exception;

class MembershipFieldTest extends TestCase
{
    public function testCleanupAndJavaScriptControl(): void
    {
        $Field = $this->createField();

        self::assertSame(42, $Field->cleanup('42'));
        self::assertSame(0, $Field->cleanup(null));
        self::assertSame(
            'package/quiqqer/memberships/bin/controls/MembershipSelect',
            $Field->getJavaScriptControl()
        );
    }

    public function testValidateAcceptsEmptyValue(): void
    {
        $this->createField()->validate(null);
        self::assertTrue(true);
    }

    public function testValidateRejectsUnknownMembership(): void
    {
        $Field = $this->createField();
        $Field->method('getId')->willReturn(12);
        $Field->method('getTitle')->willReturn('Membership');
        $Field->method('getType')->willReturn(MembershipField::TYPE);

        $this->expectException(Exception::class);
        $Field->validate(2147483647);
    }

    private function createField(): MembershipField
    {
        return $this->getMockBuilder(MembershipField::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getTitle', 'getType'])
            ->getMock();
    }
}
