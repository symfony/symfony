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
use Symfony\Component\DependencyInjection\Tests\Fixtures\ReadOnlyClass;

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

    public function testMissingInterfaceAttribute()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(TestContainer::class))
            ->setLazy(true)
            ->addTag('proxy', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid definition for service "Symfony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper\TestContainer": the "interface" attribute is missing on a "proxy" tag.');
        $dumper->getProxyCode($definition);
    }

    public function testUnknownInterface()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(TestContainer::class))
            ->setLazy(true)
            ->addTag('proxy', ['interface' => 'Not\A\Real\Interfase']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "proxy" tag for service "Symfony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper\TestContainer": "Not\A\Real\Interfase" is neither a class nor an interface.');
        $dumper->getProxyCode($definition);
    }

    public function testSeveralProxyTagsRequireInterfaces()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(TestContainer::class))
            ->setLazy(true)
            ->addTag('proxy', ['interface' => TestContainer::class])
            ->addTag('proxy', ['interface' => ContainerInterface::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "proxy" tag for service "Symfony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper\TestContainer": several "proxy" tags found but "Symfony\Component\DependencyInjection\Tests\LazyProxy\PhpDumper\TestContainer" is not an interface.');
        $dumper->getProxyCode($definition);
    }

    /**
     * @requires PHP 8.3
     */
    public function testReadonlyClass()
    {
        $dumper = new LazyServiceDumper();
        $definition = (new Definition(ReadOnlyClass::class))->setLazy(true);

        $this->assertTrue($dumper->isProxyCandidate($definition));
        $this->assertStringContainsString('readonly class ReadOnlyClassGhost', $dumper->getProxyCode($definition));
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
