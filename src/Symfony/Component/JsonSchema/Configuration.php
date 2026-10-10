<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema;

use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @experimental
 */
final class Configuration
{
    public readonly Dialect $dialect;

    /**
     * @param list<string>                                                $groups
     * @param array<int|string, mixed>|null                               $attributes
     * @param list<string>                                                $ignoredAttributes
     * @param array<string|GroupSequence|array<mixed>>|GroupSequence|null $validationGroups
     * @param string|null                                                 $format            The serializer format (e.g. "json"), unrelated to the dialect
     */
    public function __construct(
        ?Dialect $dialect = null,
        public readonly array $groups = [],
        public readonly ?array $attributes = null,
        public readonly array $ignoredAttributes = [],
        public readonly bool $allowExtraAttributes = true,
        public readonly array|GroupSequence|null $validationGroups = null,
        public readonly ?string $definitionName = null,
        public readonly ?string $definitionPrefix = null,
        public readonly ?string $format = null,
    ) {
        $this->dialect = $dialect ?? Dialect::jsonSchema202012();
    }

    /**
     * Returns null when the validator has to fall back to the default groups of the class.
     *
     * @return list<string>|null
     */
    public function getValidationGroupNames(): ?array
    {
        return match (true) {
            $this->validationGroups instanceof GroupSequence => self::flattenGroups($this->validationGroups->groups),
            \is_array($this->validationGroups) => self::flattenGroups($this->validationGroups),
            default => null,
        };
    }

    /**
     * @param array<mixed> $groups
     *
     * @return list<string>
     */
    private static function flattenGroups(array $groups): array
    {
        $flattened = [];
        foreach ($groups as $group) {
            $flattened = [...$flattened, ...match (true) {
                $group instanceof GroupSequence => self::flattenGroups($group->groups),
                \is_array($group) => self::flattenGroups($group),
                default => [(string) $group],
            }];
        }

        return array_values(array_unique($flattened));
    }
}
