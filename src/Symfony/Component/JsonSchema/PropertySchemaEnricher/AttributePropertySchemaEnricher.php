<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\PropertySchemaEnricher;

use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\DialectKeywords;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * @experimental
 */
final class AttributePropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        if (!property_exists($property->class, $property->property)) {
            return $property;
        }

        foreach ((new \ReflectionProperty($property->class, $property->property))->getAttributes(JsonSchemaConstraint::class) as $attribute) {
            $constraint = $attribute->newInstance();

            $property = $property->withSchema(self::applyConstraint($constraint, $property->schema, $config->dialect));

            if (null !== $constraint->required) {
                $property = $property->withRequired($constraint->required);
            }
        }

        return $property;
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function applyConstraint(JsonSchemaConstraint $constraint, array $schema, Dialect $dialect): array
    {
        foreach (get_object_vars($constraint) as $keyword => $value) {
            if (null === $value || 'required' === $keyword) {
                continue;
            }

            $schema = match ($keyword) {
                'enum' => DialectKeywords::enum($schema, $value),
                'const' => [...$schema, ...$dialect->const($value)],
                'exclusiveMinimum' => [...$schema, ...$dialect->exclusiveMinimum($value)],
                'exclusiveMaximum' => [...$schema, ...$dialect->exclusiveMaximum($value)],
                'example' => [...$schema, ...$dialect->example($value)],
                default => [...$schema, $keyword => $value],
            };
        }

        return $schema;
    }
}
