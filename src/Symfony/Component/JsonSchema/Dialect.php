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

/**
 * @experimental
 */
final class Dialect
{
    public function __construct(
        public readonly string $refPath,
        public readonly NullSyntax $nullSyntax,
        public readonly bool $supportsConst = true,
        public readonly bool $supportsNumericExclusiveBounds = true,
        public readonly bool $supportsExamples = true,
        public readonly bool $supportsRefSiblings = true,
        public readonly ?string $schemaUri = null,
    ) {
    }

    public static function jsonSchema202012(): self
    {
        return new self('#/$defs/', NullSyntax::Union, schemaUri: 'https://json-schema.org/draft/2020-12/schema');
    }

    public static function openApi31(): self
    {
        return new self('#/components/schemas/', NullSyntax::Union);
    }

    public static function openApi30(): self
    {
        return new self(
            '#/components/schemas/',
            NullSyntax::NullableFlag,
            supportsConst: false,
            supportsNumericExclusiveBounds: false,
            supportsExamples: false,
            supportsRefSiblings: false,
        );
    }

    public static function swagger20(): self
    {
        return new self(
            '#/definitions/',
            NullSyntax::Unsupported,
            supportsConst: false,
            supportsNumericExclusiveBounds: false,
            supportsExamples: false,
            supportsRefSiblings: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function const(mixed $value): array
    {
        return $this->supportsConst ? ['const' => $value] : ['enum' => [$value]];
    }

    /**
     * @return array<string, mixed>
     */
    public function exclusiveMinimum(int|float $value): array
    {
        return $this->supportsNumericExclusiveBounds ? ['exclusiveMinimum' => $value] : ['minimum' => $value, 'exclusiveMinimum' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function exclusiveMaximum(int|float $value): array
    {
        return $this->supportsNumericExclusiveBounds ? ['exclusiveMaximum' => $value] : ['maximum' => $value, 'exclusiveMaximum' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function example(mixed $value): array
    {
        return $this->supportsExamples ? ['examples' => [$value]] : ['example' => $value];
    }
}
