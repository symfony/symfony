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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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

    public function testFinalClassInterface()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(TestContainer::class))
            ->setLazy(true)
            ->addTag('proxy', ['interface' => ContainerInterface::class]);

        $this->assertTrue($dumper->isProxyCandidate($definition));
        $this->assertStringContainsString('function get(', $dumper->getProxyCode($definition));
    }

    public function testInvalidClass()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(\stdClass::class))
            ->setLazy(true)
            ->addTag('proxy', ['interface' => ContainerInterface::class]);

        $this->assertTrue($dumper->isProxyCandidate($definition));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "proxy" tag for service "stdClass": class "stdClass" doesn\'t implement "Psr\Container\ContainerInterface".');
        $dumper->getProxyCode($definition);
    }
}

final class TestContainer implements ContainerInterface
{
    public function has(string $key): bool
    {
        return true;
    }

    public function get(string $key): string
    {
        return $key;
    }
}
