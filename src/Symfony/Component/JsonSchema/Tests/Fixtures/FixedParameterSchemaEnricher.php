<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\ParameterSchemaEnricher\ParameterSchema;
use Symfony\Component\JsonSchema\ParameterSchemaEnricher\ParameterSchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

class FixedParameterSchemaEnricher implements ParameterSchemaEnricherInterface
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        private readonly string $parameter,
        private readonly array $schema,
        private readonly bool $required,
    ) {
    }

    public function enrich(ParameterSchema $parameter, Configuration $config, SchemaDocument $document): ParameterSchema
    {
        if ($this->parameter !== $parameter->parameter->name) {
            return $parameter;
        }

        return $parameter->withSchema($this->schema)->withRequired($this->required);
    }
}
