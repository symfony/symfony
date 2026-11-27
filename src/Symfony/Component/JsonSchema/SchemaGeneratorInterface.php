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

use Symfony\Component\TypeInfo\Type;

/**
 * Generates the JSON Schema of a type.
 *
 * Objects are described by definitions referenced from the root, the returned {@see Schema} holds both
 * and renders them for the configured dialect. {@see Schema::flatten()} inlines the definitions afterwards.
 *
 * @experimental
 */
interface SchemaGeneratorInterface
{
    /**
     * @param SchemaDocument|null $document The document collecting the definitions, to share them with other generated schemas
     */
    public function generate(Type $type, Configuration $config = new Configuration(), ?SchemaDocument $document = null): Schema;

    /**
     * Generates the schema of the arguments of a function, for example the input of a tool exposed to an AI agent.
     *
     * The root is an object with one property per parameter, in order, described by the type, the default value
     * and the @param description of the parameter, then refined by the parameter schema enrichers
     * (the #[JsonSchemaConstraint] attribute of the parameter by default).
     * Parameters that have no default value are required, unless an enricher says otherwise.
     *
     * @param list<\ReflectionParameter> $parameters
     * @param SchemaDocument|null        $document   The document collecting the definitions, to share them with other generated schemas
     */
    public function generateForParameters(array $parameters, Configuration $config = new Configuration(), ?SchemaDocument $document = null): Schema;
}
