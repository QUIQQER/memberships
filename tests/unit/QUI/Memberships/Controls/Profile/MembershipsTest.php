<?php

namespace QUI\Memberships\Controls\Profile;

use PHPUnit\Framework\TestCase;

class MembershipsTest extends TestCase
{
    public function testProfileControlRendersConfiguredTemplate(): void
    {
        $Control = new Memberships();
        $body = $Control->getBody();

        self::assertIsString($body);
        self::assertStringContainsString('quiqqer-memberships', $body);

        $Control->validate();
        $Control->onSave();
    }
}
