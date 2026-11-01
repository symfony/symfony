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
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\PropertySchemaProvider\PropertySchemaProviderInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

class FailingPropertySchemaProvider implements PropertySchemaProviderInterface
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly string $class,
    ) {
    }

    public function provide(string $class, string $property, Configuration $config, SchemaDocument $document): ?array
    {
        if ($class === $this->class) {
            throw new InvalidArgumentException(\sprintf('Cannot describe "%s::$%s".', $class, $property));
        }

        return null;
    }
}
