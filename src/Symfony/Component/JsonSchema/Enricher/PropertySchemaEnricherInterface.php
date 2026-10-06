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
 * @experimental
 */
interface PropertySchemaEnricherInterface
{
    public function enrich(PropertySchema $property, Configuration $config): PropertySchema;
}
