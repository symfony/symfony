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
use Symfony\Component\JsonSchema\Enricher\PropertySchema;
use Symfony\Component\JsonSchema\Enricher\PropertySchemaEnricherInterface;

class MarkingPropertySchemaEnricher implements PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config): PropertySchema
    {
        return $property->withSchema([...$property->schema, 'x-enriched' => true]);
    }
}
