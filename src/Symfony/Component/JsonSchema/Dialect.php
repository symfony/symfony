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
final readonly class Dialect
{
    public function __construct(
        public string $refPath,
        public NullSyntax $nullSyntax,
        public bool $supportsConst = true,
        public bool $supportsExclusiveMinAsNumber = true,
        public bool $supportsExamples = true,
        public ?string $schemaUri = null,
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
            supportsExclusiveMinAsNumber: false,
            supportsExamples: false,
        );
    }

    public static function swagger20(): self
    {
        return new self(
            '#/definitions/',
            NullSyntax::Unsupported,
            supportsConst: false,
            supportsExclusiveMinAsNumber: false,
            supportsExamples: false,
        );
    }
}
