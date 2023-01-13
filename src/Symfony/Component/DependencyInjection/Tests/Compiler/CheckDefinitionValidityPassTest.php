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
use Symfony\Component\DependencyInjection\Compiler\CheckDefinitionValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

class CheckDefinitionValidityPassTest extends TestCase
{
    public function testProcessDetectsSyntheticNonPublicDefinitions()
    {
        $this->expectException(RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a')->setSynthetic(true)->setPublic(false);

        $this->process($container);
    }

    public function testProcessDetectsNonSyntheticNonAbstractDefinitionWithoutClass()
    {
        $this->expectException(RuntimeException::class);
        $container = new ContainerBuilder();
        $container->register('a')->setSynthetic(false)->setAbstract(false);

        $this->process($container);
    }

    public function testProcessAcceptsServiceLocatorWithoutClass()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.service_locator');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessDetectsFactoryWithoutClass()
    {
        $container = new ContainerBuilder();

        $container->register('.123_anonymous_service_id_should_not_throw_~1234567')->setFactory('factory');
        $this->process($container);

        $this->expectException(RuntimeException::class);
        $container->register('.any_non_anonymous_id_throws')->setFactory('factory');

        $this->process($container);
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a', 'class');
        $container->register('b', 'class')->setSynthetic(true)->setPublic(true);
        $container->register('c', 'class')->setAbstract(true);
        $container->register('d', 'class')->setSynthetic(true);

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testValidTags()
    {
        $container = new ContainerBuilder();
        $container->register('a', 'class')->addTag('foo', ['bar' => 'baz']);
        $container->register('b', 'class')->addTag('foo', ['bar' => null]);
        $container->register('c', 'class')->addTag('foo', ['bar' => 1]);
        $container->register('d', 'class')->addTag('foo', ['bar' => 1.1]);
        $container->register('d', 'class')->addTag('foo', ['bar' => ['baz' => 'baz']]);
        $container->register('e', 'class')->addTag('foo', ['deep' => ['foo' => ['bar' => 'baz']]]);

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider provideInvalidTags
     */
    public function testInvalidTags(string $name, array $attributes, string $message)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $container = new ContainerBuilder();
        $container->register('a', 'class')->addTag($name, $attributes);
        $this->process($container);
    }

    public static function provideInvalidTags(): iterable
    {
        $message = 'A "tags" attribute must be of a scalar-type for service "a", tag "%s", attribute "%s".';
        yield 'object attribute value' => [
            'foo',
            ['bar' => new class() {}],
            sprintf($message, 'foo', 'bar'),
        ];
        yield 'nested object attribute value' => [
            'foo',
            ['bar' => ['baz' => new class() {}]],
            sprintf($message, 'foo', 'bar.baz'),
        ];
        yield 'deeply nested object attribute value' => [
            'foo',
            ['bar' => ['baz' => ['qux' => new class() {}]]],
            sprintf($message, 'foo', 'bar.baz.qux'),
        ];
    }

    public function testDynamicPublicServiceName()
    {
        $this->expectException(EnvParameterException::class);
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->register("foo.$env", 'class')->setPublic(true);

        $this->process($container);
    }

    public function testDynamicPublicAliasName()
    {
        $this->expectException(EnvParameterException::class);
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->setAlias("foo.$env", 'class')->setPublic(true);

        $this->process($container);
    }

    public function testDynamicPrivateName()
    {
        $container = new ContainerBuilder();
        $env = $container->getParameterBag()->get('env(BAR)');
        $container->register("foo.$env", 'class');
        $container->setAlias("bar.$env", 'class');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new CheckDefinitionValidityPass();
        $pass->process($container);
    }
}
