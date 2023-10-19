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
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class CheckExceptionOnInvalidReferenceBehaviorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;
        $container->register('b', '\stdClass');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessThrowsExceptionOnInvalidReference()
    {
        $this->expectException(ServiceNotFoundException::class);
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;

        $this->process($container);
    }

    public function testProcessThrowsExceptionOnInvalidReferenceFromInlinedDefinition()
    {
        $this->expectException(ServiceNotFoundException::class);
        $container = new ContainerBuilder();

        $def = new Definition();
        $def->addArgument(new Reference('b'));

        $container
            ->register('a', '\stdClass')
            ->addArgument($def)
        ;

        $this->process($container);
    }

    public function testProcessDefinitionWithBindings()
    {
        $container = new ContainerBuilder();

        $container
            ->register('b')
            ->setBindings([new BoundArgument(new Reference('a'))])
        ;

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    public function testWithErroredServiceLocator()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('The service "foo" in the container provided to "bar" has a dependency on a non-existent service "baz".');
        $container = new ContainerBuilder();

        ServiceLocatorTagPass::register($container, ['foo' => new Reference('baz')], 'bar');

        (new AnalyzeServiceReferencesPass())->process($container);
        (new InlineServiceDefinitionsPass())->process($container);
        $this->process($container);
    }

    public function testWithErroredHiddenService()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('The service "bar" has a dependency on a non-existent service "foo".');
        $container = new ContainerBuilder();

        ServiceLocatorTagPass::register($container, ['foo' => new Reference('foo')], 'bar');

        (new AnalyzeServiceReferencesPass())->process($container);
        (new InlineServiceDefinitionsPass())->process($container);
        $this->process($container);
    }

    public function testProcessThrowsExceptionOnInvalidReferenceWithAlternatives()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('The service "a" has a dependency on a non-existent service "@ccc". Did you mean this: "ccc"?');
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('@ccc'));

        $container
            ->register('ccc', '\stdClass');

        $this->process($container);
    }

    public function testCurrentIdIsExcludedFromAlternatives()
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('The service "app.my_service" has a dependency on a non-existent service "app.my_service2".');

        $container = new ContainerBuilder();
        $container
            ->register('app.my_service', \stdClass::class)
            ->addArgument(new Reference('app.my_service2'));

        $this->process($container);
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new CheckExceptionOnInvalidReferenceBehaviorPass();
        $pass->process($container);
    }
}
