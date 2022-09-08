<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\PhpDumper\LazyServiceDumper;

class LazyServiceDumperTest extends TestCase
{
    public function testProxyInterface()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(ContainerInterface::class))->setLazy(true);

        $this->assertTrue($dumper->isProxyCandidate($definition));
        $this->assertStringContainsString('function get(', $dumper->getProxyCode($definition));
    }
}
