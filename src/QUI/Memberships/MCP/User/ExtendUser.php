<?php

namespace QUI\Memberships\MCP\User;

use DateTime;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Exception;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

class ExtendUser extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
        $schema = self::getMembershipUserSchema();
        $schema['properties']['endDate'] = [
            'type' => 'string',
            'description' => 'Optional new end date in server local time: YYYY-MM-DD HH:MM:SS. '
                . 'Must be later than the current end date. Omit to extend by one configured cycle.'
        ];

        $serverBuilder->addTool(
            function (int $membershipId, int | string $userId, ?string $endDate = null): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    self::checkEditUsersPermission();
                    $Membership = Handler::getInstance()->getChild($membershipId);

                    if ($Membership->isInfinite()) {
                        throw new Exception('An infinite membership cannot be extended.');
                    }

                    $MembershipUser = $Membership->getMembershipUser($userId);

                    if ($MembershipUser->isCancelled()) {
                        throw new Exception('A cancelled membership assignment cannot be extended.');
                    }

                    $CurrentEndDate = new DateTime((string)$MembershipUser->getAttribute('endDate'));
                    $NextEndDate = $MembershipUser->getNextCycleEndDate();

                    if ($endDate !== null) {
                        $NextEndDate = DateTime::createFromFormat('!Y-m-d H:i:s', $endDate);

                        if (
                            $NextEndDate === false
                            || $NextEndDate->format('Y-m-d H:i:s') !== $endDate
                            || $NextEndDate <= $CurrentEndDate
                        ) {
                            throw new Exception('The endDate must be a valid date later than the current end date.');
                        }
                    }

                    if (!$NextEndDate instanceof DateTime || $NextEndDate <= $CurrentEndDate) {
                        throw new Exception('The extension must increase the current end date.');
                    }

                    $MembershipUser->setEditUser(Server::getRequestUser());
                    $MembershipUser->extend(false, null, $NextEndDate);
                    $MembershipUser->refresh();

                    return self::parseMembershipUser($MembershipUser);
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_users_extend',
            description: 'Manually extends an active membership assignment and records its history. '
                . 'Sends the configured manual extension email. Does not change a linked ERP contract.',
            inputSchema: $schema
        );
    }
}
