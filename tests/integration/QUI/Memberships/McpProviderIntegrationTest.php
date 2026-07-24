<?php

namespace QUI\Memberships;

use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\Memberships\MCP\Provider;

class McpProviderIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Server::setRequestUser(null);
        parent::tearDown();
    }

    public function testProviderRegistersMembershipToolsForSystemUser(): void
    {
        Server::setRequestUser(QUI::getUsers()->getSystemUser());
        $Builder = new Builder();

        (new Provider())->register($Builder);
        $tools = $Builder->getTools();

        self::assertSame([
            'quiqqer_memberships_groups_list',
            'quiqqer_memberships_list',
            'quiqqer_memberships_get',
            'quiqqer_memberships_update'
        ], array_keys($tools));

        foreach ($tools as $tool) {
            self::assertIsCallable($tool['callback']);
            self::assertNotSame('', $tool['description']);
            self::assertIsArray($tool['inputSchema']);
        }

        $groups = $tools['quiqqer_memberships_groups_list']['callback'](
            null,
            1,
            0
        );
        self::assertSame(1, $groups['limit']);
        self::assertSame(0, $groups['offset']);
        self::assertLessThanOrEqual(1, count($groups['groups']));

        $memberships = $tools['quiqqer_memberships_list']['callback'](
            'phpunit-membership-does-not-exist',
            null,
            1,
            0,
            'id',
            'ASC'
        );
        self::assertSame(0, $memberships['total']);
        self::assertSame(1, $memberships['limit']);
        self::assertSame(0, $memberships['offset']);
        self::assertSame([], $memberships['memberships']);
    }

    public function testProviderRegistersNoToolsForNobody(): void
    {
        Server::setRequestUser(new QUI\Users\Nobody());
        $Builder = new Builder();

        (new Provider())->register($Builder);

        self::assertSame([], $Builder->getTools());
    }
}
