<?php

/**
 * This file contains \QUI\Memberships\MCP\Membership\GetMembership
 */

namespace QUI\Memberships\MCP\Membership;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

class GetMembership extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (int $id): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();

                    return self::parseMembership(
                        Handler::getInstance()->getChild($id),
                        true
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_get',
            description: 'Returns the configuration and usage details of a QUIQQER membership.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Membership ID.', 'minimum' => 1]
                ]
            ]
        );
    }
}
