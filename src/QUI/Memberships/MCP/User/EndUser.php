<?php

namespace QUI\Memberships\MCP\User;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

class EndUser extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (int $membershipId, int | string $userId): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    self::checkEditUsersPermission();
                    $MembershipUser = Handler::getInstance()->getChild($membershipId)->getMembershipUser($userId);
                    $MembershipUser->setEditUser(Server::getRequestUser());
                    $MembershipUser->delete();
                    $MembershipUser->refresh();

                    return self::parseMembershipUser($MembershipUser);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_users_end',
            description: 'Immediately ends an active membership assignment by archiving it and removing '
                . 'groups granted exclusively by this membership. Keeps history and does not cancel a linked ERP contract.',
            inputSchema: self::getMembershipUserSchema()
        );
    }
}
