<?php

namespace QUI\Memberships\MCP\Membership;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Exception;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use QUI\Permissions\Permission;
use Throwable;

class DeleteMembership extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (int $id): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    Permission::checkPermission(Handler::PERMISSION_DELETE, Server::getRequestUser());
                    $Membership = Handler::getInstance()->getChild($id);

                    if ($Membership->isLocked(Server::getRequestUser())) {
                        throw new Exception('The membership is currently locked and cannot be deleted.');
                    }

                    $Membership->setEditUser(Server::getRequestUser());
                    $Membership->delete();

                    return ['id' => $id, 'deleted' => true];
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_delete',
            description: 'Deletes a membership definition only when no active users remain. '
                . 'Linked products are deactivated by the existing membership deletion workflow.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Membership definition ID.']
                ]
            ]
        );
    }
}
