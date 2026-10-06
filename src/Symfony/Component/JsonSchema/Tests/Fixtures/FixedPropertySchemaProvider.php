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
use Symfony\Component\JsonSchema\Enricher\PropertySchemaProviderInterface;

class FixedPropertySchemaProvider implements PropertySchemaProviderInterface
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $schema
     */
    public function __construct(
        private readonly string $class,
        private readonly string $property,
        private readonly array $schema,
    ) {
    }

    public function provide(string $class, string $property, Configuration $config): ?array
    {
        return $class === $this->class && $property === $this->property ? $this->schema : null;
    }
}
