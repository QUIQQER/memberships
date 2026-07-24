<?php

namespace QUI\Memberships\MCP;

use Mcp\Server\Builder;
use QUI\Memberships\Membership;

class TestableAbstractTool extends AbstractTool
{
    public function register(Builder $serverBuilder): void
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseMembershipPublic(Membership $Membership, bool $withDetails = false): array
    {
        return parent::parseMembership($Membership, $withDetails);
    }

    /**
     * @return array<string, string>
     */
    public static function parseTranslationsPublic(mixed $value): array
    {
        return parent::parseTranslations($value);
    }

    public static function sanitizeLimitPublic(?int $limit): int
    {
        return parent::sanitizeLimit($limit);
    }

    /**
     * @param array<mixed> $groupIds
     * @return int[]
     */
    public static function validateGroupIdsPublic(array $groupIds): array
    {
        return parent::validateGroupIds($groupIds);
    }

    public static function checkMembershipsPermissionPublic(): void
    {
        parent::checkMembershipsPermission();
    }

    public static function checkEditPermissionPublic(): void
    {
        parent::checkEditPermission();
    }
}
