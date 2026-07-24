<?php

/**
 * This file contains \QUI\Memberships\MCP\Group\ListGroups
 */

namespace QUI\Memberships\MCP\Group;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\ToolHelper;
use QUI\Groups\Group;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function max;
use function stripos;

class ListGroups extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                string | null $search = null,
                int | null $limit = null,
                int | null $offset = null
            ): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();

                    $groups = [];

                    foreach (\QUI::getGroups()->getAllGroups() as $group) {
                        if ($group instanceof Group) {
                            $groups[] = [
                                'id' => (int)$group->getId(),
                                'uuid' => (string)$group->getUUID(),
                                'name' => $group->getName(),
                                'parent' => (string)$group->getAttribute('parent'),
                                'active' => (bool)$group->getAttribute('active')
                            ];
                            continue;
                        }

                        $groups[] = [
                            'id' => (int)($group['id'] ?? 0),
                            'uuid' => (string)($group['uuid'] ?? ''),
                            'name' => (string)($group['name'] ?? ''),
                            'parent' => (string)($group['parent'] ?? ''),
                            'active' => (bool)($group['active'] ?? false)
                        ];
                    }

                    if ($search !== null && $search !== '') {
                        $groups = array_values(array_filter(
                            $groups,
                            static fn(array $group): bool => stripos($group['name'], $search) !== false
                                || (string)$group['id'] === $search
                                || $group['uuid'] === $search
                        ));
                    }

                    $total = count($groups);
                    $limit = self::sanitizeLimit($limit);
                    $offset = (int)max(0, $offset ?? 0);

                    return [
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'groups' => array_slice($groups, $offset, $limit)
                    ];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_groups_list',
            description: 'Lists QUIQQER user groups that can be assigned to memberships.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'search' => [
                        'type' => 'string',
                        'description' => 'Optional group name, ID or UUID.'
                    ],
                    'limit' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
                    'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0]
                ]
            ]
        );
    }
}
