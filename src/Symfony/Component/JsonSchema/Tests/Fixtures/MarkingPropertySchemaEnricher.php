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
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

class MarkingPropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config, SchemaDocument $document): PropertySchema
    {
        return $property->withSchema([...$property->schema, 'x-enriched' => true]);
    }
}
