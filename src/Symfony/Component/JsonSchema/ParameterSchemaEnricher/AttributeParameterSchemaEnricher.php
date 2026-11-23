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

use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * @experimental
 */
final class AttributeParameterSchemaEnricher implements ParameterSchemaEnricherInterface
{
    public function enrich(ParameterSchema $parameter, Configuration $config, SchemaDocument $document): ParameterSchema
    {
        foreach ($parameter->parameter->getAttributes(JsonSchemaConstraint::class) as $attribute) {
            $constraint = $attribute->newInstance();

            $parameter = $parameter->withSchema(AttributePropertySchemaEnricher::applyConstraint($constraint, $parameter->schema, $config->dialect));

            if (null !== $constraint->required) {
                $parameter = $parameter->withRequired($constraint->required);
            }
        }

        return $parameter;
    }
}
