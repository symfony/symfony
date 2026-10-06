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

use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @experimental
 */
final readonly class Configuration
{
    public Dialect $dialect;

    /**
     * @param list<string>                                                         $groups
     * @param array<int|string, mixed>|null                                        $attributes
     * @param list<string>                                                         $ignoredAttributes
     * @param array<string|GroupSequence|array<mixed>>|GroupSequence|\Closure|null $validationGroups
     * @param string|null                                                          $format            The serializer format (e.g. "json"), unrelated to the dialect
     */
    public function __construct(
        ?Dialect $dialect = null,
        public ReferenceStrategy $references = ReferenceStrategy::ByDefinition,
        public array $groups = [],
        public ?array $attributes = null,
        public array $ignoredAttributes = [],
        public bool $allowExtraAttributes = true,
        public array|GroupSequence|\Closure|null $validationGroups = null,
        public ?string $definitionName = null,
        public ?string $definitionPrefix = null,
        public ?string $format = null,
    ) {
        $this->dialect = $dialect ?? Dialect::jsonSchema202012();
    }

    public function with(mixed ...$changes): self
    {
        $values = get_object_vars($this);

        if ($unknown = array_diff_key($changes, $values)) {
            throw new InvalidArgumentException(\sprintf('Unknown configuration "%s", expected one of "%s".', implode('", "', array_keys($unknown)), implode('", "', array_keys($values))));
        }

        return new self(...array_replace($values, $changes));
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
