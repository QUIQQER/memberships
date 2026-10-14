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
use QUI\Memberships\MCP\Membership\CreateMembership;
use QUI\Memberships\MCP\Membership\DeleteMembership;
use QUI\Memberships\MCP\Membership\GetMembership;
use QUI\Memberships\MCP\Membership\ListMemberships;
use QUI\Memberships\MCP\Membership\UpdateMembership;
use QUI\Memberships\MCP\User\AssignUser;
use QUI\Memberships\MCP\User\EndUser;
use QUI\Memberships\MCP\User\ExtendUser;
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
            new UpdateMembership(),
            new CreateMembership(),
            new DeleteMembership(),
            new AssignUser(),
            new ExtendUser(),
            new EndUser()
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
