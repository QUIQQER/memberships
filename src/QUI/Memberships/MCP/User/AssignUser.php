<?php

namespace QUI\Memberships\MCP\User;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Exception;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use Throwable;

class AssignUser extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (int $membershipId, int | string $userId): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    self::checkEditUsersPermission();
                    $Membership = Handler::getInstance()->getChild($membershipId);

                    if ($Membership->hasMembershipUserId($userId)) {
                        throw new Exception('The user is already assigned. Use quiqqer_memberships_users_extend.');
                    }

                    $Created = MembershipUsersHandler::getInstance()->createChild([
                        'membershipId' => $membershipId,
                        'userId' => $userId
                    ], Server::getRequestUser());

                    return self::parseMembershipUser(
                        MembershipUsersHandler::getInstance()->getChild($Created->getId())
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_users_assign',
            description: 'Assigns a QUIQQER user to a membership and grants its groups. '
                . 'Rejects existing active assignments without extending them.',
            inputSchema: self::getMembershipUserSchema()
        );
    }
}
