<?php

declare(strict_types=1);

namespace QUITests\Memberships\Unit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use QUI\Utils\XML\Settings;

class UserSettingsTest extends TestCase
{
    public function testUserExtensionUsesNativeCategoriesAndKeepsReminderAttribute(): void
    {
        $Document = new DOMDocument();
        self::assertTrue($Document->load($this->userXml()));
        $Path = new DOMXPath($Document);

        self::assertSame(0.0, $Path->evaluate('count(//window/tab | //template)'));
        self::assertSame('7', $Path->evaluate('string(//category[@name="memberships"]/@index)'));
        self::assertSame(
            'quiqqer.memberships.cancel_reminder_sent',
            $Path->evaluate('string(//user/attributes/attribute)')
        );
    }

    public function testSettingsRendererProvidesTheMembershipControlImportContainer(): void
    {
        $Settings = new Settings();
        $Settings->setXMLPath('//user/window');
        $html = $Settings->getCategoriesHtml([$this->userXml()], 'memberships');
        $Document = new DOMDocument();
        self::assertTrue($Document->loadHTML('<meta charset="UTF-8">' . $html));
        $Path = new DOMXPath($Document);
        $Inputs = $Path->query('//input[@data-name="user-memberships"]');
        self::assertCount(1, $Inputs);
        $Input = $Inputs->item(0);

        self::assertInstanceOf(DOMElement::class, $Input);
        self::assertSame('hidden', $Input->getAttribute('type'));
        self::assertSame(
            'package/quiqqer/memberships/bin/controls/user/UserMemberships',
            $Input->getAttribute('data-qui')
        );
        // The control replaces this table when imported to keep the list at full panel height.
        self::assertSame(1.0, $Path->evaluate('count(ancestor::table)', $Input));
    }

    private function userXml(): string
    {
        return dirname(__DIR__, 2) . '/user.xml';
    }
}
