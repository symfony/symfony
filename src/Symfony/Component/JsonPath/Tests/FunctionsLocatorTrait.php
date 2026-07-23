<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use Psr\Container\ContainerInterface;

trait FunctionsLocatorTrait
{
    private function createFunctionsLocator(array $functions): ContainerInterface
    {
        return new class($functions) implements ContainerInterface {
            public function __construct(private array $functions)
            {
            }

            public function get(string $id): mixed
            {
                if (!isset($this->functions[$id])) {
                    throw new \RuntimeException(\sprintf('Unknown custom JSONPath function "%s".', $id));
                }

                return $this->functions[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->functions[$id]);
            }
        };
    }
}
