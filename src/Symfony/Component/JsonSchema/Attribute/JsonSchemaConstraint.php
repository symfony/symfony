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

use Symfony\Component\JsonSchema\Direction;

/**
 * Sets JSON Schema keywords on a property. A null argument leaves the keyword unset.
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class JsonSchemaConstraint
{
    /**
     * @param list<mixed>|null $enum
     * @param Direction|null   $applyTo restricts the keywords to schemas generated for this direction
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $format = null,
        public ?array $enum = null,
        public mixed $const = null,
        public ?string $pattern = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public int|float|null $exclusiveMinimum = null,
        public int|float|null $exclusiveMaximum = null,
        public int|float|null $multipleOf = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public mixed $default = null,
        public mixed $example = null,
        public ?bool $deprecated = null,
        public ?bool $readOnly = null,
        public ?bool $writeOnly = null,
        public ?bool $required = null,
        public ?Direction $applyTo = null,
    ) {
    }
}
