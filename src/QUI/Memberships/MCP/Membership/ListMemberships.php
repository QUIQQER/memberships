<?php

/**
 * This file contains \QUI\Memberships\MCP\Membership\ListMemberships
 */

namespace QUI\Memberships\MCP\Membership;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

class ListMemberships extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                string | null $search = null,
                int | null $userId = null,
                int | null $limit = null,
                int | null $offset = null,
                string | null $sortOn = null,
                string | null $sortBy = null
            ): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();

                    $limit = self::sanitizeLimit($limit);
                    $offset = (int)max(0, $offset ?? 0);
                    $sortOn = in_array($sortOn, ['id', 'title', 'createDate', 'editDate'], true)
                        ? $sortOn
                        : 'id';
                    $sortBy = strtoupper((string)$sortBy) === 'DESC' ? 'DESC' : 'ASC';
                    $searchParams = [
                        'search' => $search,
                        'userId' => $userId,
                        'limit' => $offset . ',' . $limit,
                        'sortOn' => $sortOn,
                        'sortBy' => $sortBy
                    ];
                    $Memberships = Handler::getInstance();
                    $memberships = [];

                    foreach ($Memberships->search($searchParams) as $membershipId) {
                        $Membership = $Memberships->getChild($membershipId);
                        $memberships[] = self::parseMembership($Membership);
                    }

                    return [
                        'total' => $Memberships->search($searchParams, true),
                        'limit' => $limit,
                        'offset' => $offset,
                        'memberships' => $memberships
                    ];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_list',
            description: 'Lists and searches QUIQQER memberships.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Search term for membership titles, descriptions and content.'
                    ],
                    'userId' => [
                        'type' => 'integer',
                        'description' => 'Only return memberships assigned to this user.',
                        'minimum' => 1
                    ],
                    'limit' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                    'sortOn' => [
                        'type' => 'string',
                        'enum' => ['id', 'title', 'createDate', 'editDate'],
                        'default' => 'id'
                    ],
                    'sortBy' => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'ASC']
                ]
            ]
        );
    }
}
