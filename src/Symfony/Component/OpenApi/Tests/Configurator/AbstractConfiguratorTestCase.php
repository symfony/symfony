<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use PHPUnit\Framework\TestCase;

abstract class AbstractConfiguratorTestCase extends TestCase
{
    /**
     * @template C
     * @template M
     *
     * @param class-string<C> $configuratorClass
     * @param class-string<M> $modelClass
     *
     * @return array{0: C, 1: M}
     */
    protected function createConfiguratorMock(string $configuratorClass, string $modelClass): array
    {
        $configurator = $this->createMock($configuratorClass);
        $configurator->method('build')->willReturn($model = $this->createMock($modelClass));

        return [$configurator, $model];
    }
}
