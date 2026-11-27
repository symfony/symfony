<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\ParameterSchemaEnricher;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGeneratorInterface;

/**
 * Refines the schema of a function parameter once it is generated (descriptions, validation constraints, examples...).
 *
 * Enrichers run in order on every parameter given to {@see SchemaGeneratorInterface::generateForParameters()},
 * each one receiving the result of the previous one. The schema already holds the type schema, the default value
 * and the @param description. It may be a reference or wrapped for null, keywords that only make sense on
 * the value type must account for it.
 *
 * {@see ParameterSchema::$required} starts as true for a parameter that is not optional; enrichers can override it.
 *
 * @experimental
 */
interface ParameterSchemaEnricherInterface
{
    public function enrich(ParameterSchema $parameter, Configuration $config, SchemaDocument $document): ParameterSchema;
}
