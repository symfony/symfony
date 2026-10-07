<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Attribute;

/**
 * Sets JSON Schema keywords on a property. A null argument leaves the keyword unset.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class JsonSchemaConstraint
{
    /**
     * @param list<mixed>|null $enum
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $format = null,
        public readonly ?array $enum = null,
        public readonly mixed $const = null,
        public readonly ?string $pattern = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly int|float|null $exclusiveMinimum = null,
        public readonly int|float|null $exclusiveMaximum = null,
        public readonly int|float|null $multipleOf = null,
        public readonly ?int $minItems = null,
        public readonly ?int $maxItems = null,
        public readonly ?bool $uniqueItems = null,
        public readonly ?int $minProperties = null,
        public readonly ?int $maxProperties = null,
        public readonly mixed $default = null,
        public readonly mixed $example = null,
        public readonly ?bool $deprecated = null,
        public readonly ?bool $readOnly = null,
        public readonly ?bool $writeOnly = null,
        public readonly ?bool $required = null,
    ) {
    }
}
