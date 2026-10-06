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
 * Supplies the whole schema of a property, replacing the one generated from its type.
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
    public function provide(string $class, string $property, Configuration $config): ?array;
}
