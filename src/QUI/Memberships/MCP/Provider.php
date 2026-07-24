<?php

/**
 * This file contains \QUI\Memberships\MCP\Provider
 */

namespace QUI\Memberships\MCP;

use Mcp\Server\Builder;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\MCP\ToolInterface;
use QUI\Memberships\MCP\Group\ListGroups;
use QUI\Memberships\MCP\Membership\GetMembership;
use QUI\Memberships\MCP\Membership\ListMemberships;
use QUI\Memberships\MCP\Membership\UpdateMembership;
use QUI\Permissions\Permission;
use Throwable;

/**
 * Memberships MCP provider
 */
class Provider implements ProviderInterface
{
    /**
     * @var array<ToolInterface>
     */
    protected array $tools;

    public function __construct()
    {
        $this->tools = [
            new ListGroups(),
            new ListMemberships(),
            new GetMembership(),
            new UpdateMembership()
        ];
    }

    public function register(Builder $serverBuilder): void
    {
        if (!$this->canUseMcp()) {
            return;
        }

        foreach ($this->tools as $Tool) {
            $Tool->register($serverBuilder);
        }
    }

    protected function canUseMcp(): bool
    {
        try {
            Permission::checkPermission(
                AbstractTool::MEMBERSHIPS_MCP_PERMISSION,
                Server::getRequestUser()
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
