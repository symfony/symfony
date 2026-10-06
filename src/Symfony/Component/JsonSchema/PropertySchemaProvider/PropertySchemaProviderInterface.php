<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\PropertySchemaProvider;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * Supplies the whole schema of a property, replacing the one generated from its type.
 *
 * Providers are asked in order for each property and the first non-null schema wins.
 * It is used as is: the property type is not visited, no definition is created and no null
 * syntax is applied. The default value, readOnly/writeOnly and the {@see PropertySchemaEnricherInterface}
 * still apply on top of it.
 *
 * The configuration is the one of the class owning the property.
 *
 * {@see SchemaDocument::describe()} gives the schema of other types, to reference them from the provided schema.
 *
 * @experimental
 */
interface PropertySchemaProviderInterface
{
    /**
     * @param class-string $class
     *
     * @return array<string, mixed>|null null to let the schema be generated from the property type
     */
    public function provide(string $class, string $property, Configuration $config, SchemaDocument $document): ?array;
}
