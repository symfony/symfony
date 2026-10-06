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

use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DialectKeywords;

/**
 * @experimental
 */
final class AttributePropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config): PropertySchema
    {
        if (!property_exists($property->class, $property->property)) {
            return $property;
        }

        foreach ((new \ReflectionProperty($property->class, $property->property))->getAttributes(JsonSchemaConstraint::class) as $attribute) {
            $constraint = $attribute->newInstance();

            $property = $property->withSchema($this->apply($constraint, $property->schema, $config));

            if (null !== $constraint->required) {
                $property = $property->withRequired($constraint->required);
            }
        }

        return $property;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function apply(JsonSchemaConstraint $constraint, array $schema, Configuration $config): array
    {
        foreach (get_object_vars($constraint) as $keyword => $value) {
            if (null === $value || 'required' === $keyword) {
                continue;
            }

            $schema = match ($keyword) {
                'enum' => DialectKeywords::enum($schema, $value),
                'const' => [...$schema, ...DialectKeywords::constant($value, $config->dialect)],
                'exclusiveMinimum' => [...$schema, ...DialectKeywords::exclusiveMinimum($value, $config->dialect)],
                'exclusiveMaximum' => [...$schema, ...DialectKeywords::exclusiveMaximum($value, $config->dialect)],
                'example' => [...$schema, ...DialectKeywords::example($value, $config->dialect)],
                default => [...$schema, $keyword => $value],
            };
        }

        return $schema;
    }
}
