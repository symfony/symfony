<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\FactoryDummy;

class AbstractRecursivePassTest extends TestCase
{
    public function testGetConstructorResolvesFactoryChildDefinitionsClass()
    {
        $container = new ContainerBuilder();
        $container->setParameter('factory_dummy_class', FactoryDummy::class);
        $container
            ->register('parent', '%factory_dummy_class%')
            ->setAbstract(true);
        $container->setDefinition('child', new ChildDefinition('parent'));
        $container
            ->register('foo', \stdClass::class)
            ->setFactory([new Reference('child'), 'createFactory']);

        $pass = new class() extends AbstractRecursivePass {
            public $actual;

            protected function processValue($value, $isRoot = false): mixed
            {
                if ($value instanceof Definition && 'foo' === $this->currentId) {
                    $this->actual = $this->getConstructor($value, true);
                }

                return parent::processValue($value, $isRoot);
            }
        };
        $pass->process($container);

        $this->assertInstanceOf(\ReflectionMethod::class, $pass->actual);
        $this->assertSame(FactoryDummy::class, $pass->actual->class);
    }

    public function testGetConstructorResolvesChildDefinitionsClass()
    {
        $container = new ContainerBuilder();
        $container
            ->register('parent', Bar::class)
            ->setAbstract(true);
        $container->setDefinition('foo', new ChildDefinition('parent'));

        $pass = new class() extends AbstractRecursivePass {
            public $actual;

            protected function processValue($value, $isRoot = false): mixed
            {
                if ($value instanceof Definition && 'foo' === $this->currentId) {
                    $this->actual = $this->getConstructor($value, true);
                }

                return parent::processValue($value, $isRoot);
            }
        };
        $pass->process($container);

        $this->assertInstanceOf(\ReflectionMethod::class, $pass->actual);
        $this->assertSame(Bar::class, $pass->actual->class);
    }

    public function testGetReflectionMethodResolvesChildDefinitionsClass()
    {
        $container = new ContainerBuilder();
        $container
            ->register('parent', Bar::class)
            ->setAbstract(true);
        $container->setDefinition('foo', new ChildDefinition('parent'));

        $pass = new class() extends AbstractRecursivePass {
            public $actual;

            protected function processValue($value, $isRoot = false): mixed
            {
                if ($value instanceof Definition && 'foo' === $this->currentId) {
                    $this->actual = $this->getReflectionMethod($value, 'create');
                }

                return parent::processValue($value, $isRoot);
            }
        };
        $pass->process($container);

        $this->assertInstanceOf(\ReflectionMethod::class, $pass->actual);
        $this->assertSame(Bar::class, $pass->actual->class);
    }

    public function testGetConstructorDefinitionNoClass()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid service "foo": the class is not set.');

        $container = new ContainerBuilder();
        $container->register('foo');

        (new class() extends AbstractRecursivePass {
            protected function processValue($value, $isRoot = false): mixed
            {
                if ($value instanceof Definition && 'foo' === $this->currentId) {
                    $this->getConstructor($value, true);
                }

                return parent::processValue($value, $isRoot);
            }
        })->process($container);
    }
}
