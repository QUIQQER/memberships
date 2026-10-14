<?php

namespace QUI\Memberships\MCP\Membership;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use QUI\Permissions\Permission;
use Throwable;

class CreateMembership extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (string $title, array $groupIds): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    Permission::checkPermission(Handler::PERMISSION_CREATE, Server::getRequestUser());

                    $Membership = Handler::getInstance()->createChild([
                        'title' => $title,
                        'groupIds' => self::validateGroupIds($groupIds)
                    ], Server::getRequestUser());

                    return self::parseMembership(
                        Handler::getInstance()->getChild($Membership->getId()),
                        true
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_create',
            description: 'Creates a membership definition with a one-month duration and no automatic renewal. '
                . 'Use quiqqer_memberships_update to configure duration, renewal and translations.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['title', 'groupIds'],
                'properties' => [
                    'title' => ['type' => 'string', 'minLength' => 1],
                    'groupIds' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'uniqueItems' => true,
                        'items' => ['type' => 'integer', 'minimum' => 1]
                    ]
                ]
            ]
        );
    }
}
