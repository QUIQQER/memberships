<?php

/**
 * This file contains \QUI\Memberships\MCP\Membership\UpdateMembership
 */

namespace QUI\Memberships\MCP\Membership;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Exception;
use QUI\Memberships\Handler;
use QUI\Memberships\MCP\AbstractTool;
use Throwable;

use function array_key_exists;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function json_encode;
use function preg_match;

class UpdateMembership extends AbstractTool
{
    private const TRANSLATABLE_ATTRIBUTES = [
        'title',
        'description',
        'content'
    ];

    public function register(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (int $id, array $attributes): CallToolResult | array {
                try {
                    self::checkMembershipsPermission();
                    self::checkEditPermission();

                    if (empty($attributes)) {
                        throw new Exception('At least one membership attribute is required.');
                    }

                    $Membership = Handler::getInstance()->getChild($id);

                    if ($Membership->isLocked()) {
                        throw new Exception('The membership is currently locked and cannot be updated.');
                    }

                    $membershipAttributes = $Membership->getAttributes();

                    // Membership::update() expects the unwrapped comma-separated value.
                    $membershipAttributes['groupIds'] = implode(',', $Membership->getGroupIds());

                    if (array_key_exists('groupIds', $attributes)) {
                        if (!is_array($attributes['groupIds'])) {
                            throw new Exception('The groupIds attribute must be an array.');
                        }

                        $membershipAttributes['groupIds'] = implode(
                            ',',
                            self::validateGroupIds($attributes['groupIds'])
                        );
                    }

                    if (array_key_exists('duration', $attributes)) {
                        if (!is_string($attributes['duration'])) {
                            throw new Exception('The duration attribute must be a string.');
                        }

                        if (
                            $attributes['duration'] !== 'infinite'
                            && preg_match(
                                '/^[1-9][0-9]*-(minute|hour|day|week|month|year)$/',
                                $attributes['duration']
                            ) !== 1
                        ) {
                            throw new Exception(
                                'The duration must be "infinite" or use the format "<number>-<period>".'
                            );
                        }

                        $membershipAttributes['duration'] = $attributes['duration'];
                    }

                    if (array_key_exists('autoExtend', $attributes)) {
                        if (!is_bool($attributes['autoExtend'])) {
                            throw new Exception('The autoExtend attribute must be a boolean.');
                        }

                        $membershipAttributes['autoExtend'] = $attributes['autoExtend'] ? 1 : 0;
                    }

                    if (array_key_exists('paymentInterval', $attributes)) {
                        if (
                            $attributes['paymentInterval'] !== null
                            && !is_string($attributes['paymentInterval'])
                        ) {
                            throw new Exception('The paymentInterval attribute must be a string or null.');
                        }

                        $membershipAttributes['paymentInterval'] = $attributes['paymentInterval'];
                    }

                    foreach (self::TRANSLATABLE_ATTRIBUTES as $attribute) {
                        if (!array_key_exists($attribute, $attributes)) {
                            continue;
                        }

                        if (!is_array($attributes[$attribute])) {
                            throw new Exception($attribute . ' must be an object with language keys.');
                        }

                        $translations = array_merge(
                            self::parseTranslations($membershipAttributes[$attribute] ?? null),
                            self::parseTranslations(json_encode($attributes[$attribute]))
                        );

                        if (empty($translations)) {
                            throw new Exception($attribute . ' must contain at least one translated text.');
                        }

                        $membershipAttributes[$attribute] = json_encode(
                            $translations,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        );
                    }

                    $Membership->setAttributes($membershipAttributes);
                    $Membership->setEditUser(Server::getRequestUser());
                    $Membership->update();

                    return self::parseMembership(
                        Handler::getInstance()->getChild($id),
                        true
                    );
                } catch (Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'quiqqer_memberships_update',
            description: 'Updates an existing QUIQQER membership. Existing membership users are not synchronized.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['id', 'attributes'],
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Membership ID.', 'minimum' => 1],
                    'attributes' => [
                        'type' => 'object',
                        'description' => 'Membership fields to update.',
                        'additionalProperties' => false,
                        'minProperties' => 1,
                        'properties' => [
                            'title' => self::getTranslationsSchema('Localized membership title.'),
                            'description' => self::getTranslationsSchema('Localized short description.'),
                            'content' => self::getTranslationsSchema('Localized detailed content.'),
                            'groupIds' => [
                                'type' => 'array',
                                'description' => 'Group IDs granted by this membership.',
                                'minItems' => 1,
                                'uniqueItems' => true,
                                'items' => ['type' => 'integer', 'minimum' => 1]
                            ],
                            'duration' => [
                                'type' => 'string',
                                'description' => 'Duration such as "1-month" or "infinite".'
                            ],
                            'autoExtend' => ['type' => 'boolean'],
                            'paymentInterval' => [
                                'type' => ['string', 'null'],
                                'description' => 'Optional payment interval.'
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function getTranslationsSchema(string $description): array
    {
        return [
            'type' => 'object',
            'description' => $description,
            'minProperties' => 1,
            'additionalProperties' => ['type' => 'string']
        ];
    }
}
