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
     * @var list<string>
     */
    public readonly array $groups;

    /**
     * @var list<string>
     */
    public readonly array $ignoredAttributes;

    /**
     * Groups and ignored attributes are sorted, as their order does not change the shape.
     *
     * The context is not part of the definition names: an extension that changes a definition according to the context needs a definition policy that adds it to the name.
     *
     * @param list<string>                                                $groups
     * @param array<int|string, mixed>|null                               $attributes
     * @param list<string>                                                $ignoredAttributes
     * @param array<string|GroupSequence|array<mixed>>|GroupSequence|null $validationGroups
     * @param string|null                                                 $format            The serializer format (e.g. "json"), unrelated to the dialect
     * @param Direction|null                                              $direction         Null to describe both the input and the output with readOnly and writeOnly keywords
     * @param array<string, mixed>                                        $context           Options for the extensions and the PropertyInfo extractors, for example the API operation being documented
     */
    public function __construct(
        ?Dialect $dialect = null,
        array $groups = [],
        public readonly ?array $attributes = null,
        array $ignoredAttributes = [],
        public readonly bool $allowExtraAttributes = true,
        public readonly array|GroupSequence|null $validationGroups = null,
        public readonly ?string $definitionName = null,
        public readonly ?string $definitionPrefix = null,
        public readonly ?string $format = null,
        public readonly ?Direction $direction = null,
        public readonly array $context = [],
    ) {
        $this->dialect = $dialect ?? Dialect::jsonSchema202012();
        $this->groups = self::sortUnique($groups);
        $this->ignoredAttributes = self::sortUnique($ignoredAttributes);
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
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sortUnique(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
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
