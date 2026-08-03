<?php

/**
 * This file contains \QUI\Memberships\MCP\AbstractTool
 */

namespace QUI\Memberships\MCP;

use QUI\AI\MCP\Server;
use QUI\Exception;
use QUI\MCP\ToolInterface;
use QUI\Memberships\Handler;
use QUI\Memberships\Membership;
use QUI\Permissions\Permission;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function min;

abstract class AbstractTool implements ToolInterface
{
    public const MEMBERSHIPS_MCP_PERMISSION = 'quiqqer.memberships.mcp';

    protected static function checkMembershipsPermission(): void
    {
        Permission::checkPermission(
            self::MEMBERSHIPS_MCP_PERMISSION,
            Server::getRequestUser()
        );
    }

    protected static function checkEditPermission(): void
    {
        Permission::checkPermission(
            Handler::PERMISSION_EDIT,
            Server::getRequestUser()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parseMembership(Membership $Membership, bool $withDetails = false): array
    {
        $result = [
            'id' => (int)$Membership->getId(),
            'title' => $Membership->getTitle(),
            'description' => $Membership->getDescription(),
            'duration' => $Membership->getAttribute('duration'),
            'groupIds' => $Membership->getGroupIds(),
            'groups' => self::parseMembershipGroups($Membership),
            'autoExtend' => $Membership->isAutoExtend(),
            'paymentInterval' => $Membership->getAttribute('paymentInterval'),
            'userCount' => count($Membership->getMembershipUserIds())
        ];

        if (!$withDetails) {
            return $result;
        }

        $result['content'] = $Membership->getContent();
        $result['translations'] = [
            'title' => self::parseTranslations($Membership->getAttribute('title')),
            'description' => self::parseTranslations($Membership->getAttribute('description')),
            'content' => self::parseTranslations($Membership->getAttribute('content'))
        ];
        $result['createDate'] = $Membership->getAttribute('createDate');
        $result['createUser'] = $Membership->getAttribute('createUser');
        $result['editDate'] = $Membership->getAttribute('editDate');
        $result['editUser'] = $Membership->getAttribute('editUser');

        return $result;
    }

    /**
     * @return array<string, string>
     */
    protected static function parseTranslations(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $translations = json_decode($value, true);

        if (!is_array($translations)) {
            return [];
        }

        return array_filter(
            $translations,
            static fn(mixed $translation): bool => is_string($translation)
        );
    }

    /**
     * @return array<int, array{id: int|string, uuid: string, name: string}>
     */
    protected static function parseMembershipGroups(Membership $Membership): array
    {
        $groups = [];
        $Groups = \QUI::getGroups();

        foreach ($Membership->getGroupIds() as $groupId) {
            try {
                $Group = $Groups->get($groupId);
                $groups[] = [
                    'id' => (int)$Group->getId(),
                    'uuid' => (string)$Group->getUUID(),
                    'name' => $Group->getName()
                ];
            } catch (\Throwable) {
                $groups[] = [
                    'id' => $groupId,
                    'uuid' => '',
                    'name' => ''
                ];
            }
        }

        return $groups;
    }

    protected static function sanitizeLimit(?int $limit): int
    {
        if (empty($limit)) {
            return 20;
        }

        return (int)min(100, max(1, $limit));
    }

    /**
     * @param array<mixed> $groupIds
     * @return int[]
     * @throws Exception
     */
    protected static function validateGroupIds(array $groupIds): array
    {
        $groupIds = array_values(array_map('intval', $groupIds));
        $groupIds = array_values(array_filter(
            $groupIds,
            static fn(int $groupId): bool => $groupId > 0
        ));
        $groupIds = array_values(array_unique($groupIds));

        if (empty($groupIds)) {
            throw new Exception('At least one valid group ID is required.');
        }

        $Groups = \QUI::getGroups();

        foreach ($groupIds as $groupId) {
            $Groups->get($groupId);
        }

        return $groupIds;
    }
}
