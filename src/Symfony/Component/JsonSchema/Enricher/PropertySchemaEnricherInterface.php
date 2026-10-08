<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Enricher;

use Symfony\Component\JsonSchema\Configuration;

/**
 * Refines the schema of a property once it is generated (descriptions, validation constraints, examples...).
 *
 * Enrichers run in order on every property of a definition, each one receiving the result
 * of the previous one. The schema already holds the type schema (or the provided one), the default
 * value and readOnly/writeOnly. It may be a reference or wrapped for null ({"anyOf": [..., {"type": "null"}]},
 * "nullable", or "allOf" around a reference depending on the dialect), keywords that only make sense on
 * the value type must account for it.
 *
 * {@see PropertySchema::$required} starts as true for a public constructor argument without default value
 * (the serializer cannot instantiate the class without it) and false otherwise; enrichers can override it.
 * A nullable property can still be required.
 *
 * The configuration is the one of the class owning the property. Since a definition is built once
 * per name, an enricher depending on something not encoded in the name sees the first occurrence only.
 *
 * @experimental
 */
interface PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config): PropertySchema;
}
