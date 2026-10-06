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
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;

/**
 * @experimental
 */
final class DescriptionPropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function __construct(
        private readonly PropertyDescriptionExtractorInterface $descriptionExtractor,
    ) {
    }

    public function enrich(PropertySchema $property, Configuration $config): PropertySchema
    {
        if (isset($property->schema['description']) || null === $description = $this->descriptionExtractor->getShortDescription($property->class, $property->property)) {
            return $property;
        }

        return $property->withSchema([...$property->schema, 'description' => $description]);
    }
}
