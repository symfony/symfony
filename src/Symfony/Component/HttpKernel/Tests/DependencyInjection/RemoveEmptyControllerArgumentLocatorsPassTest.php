<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveInvalidReferencesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass;

class RemoveEmptyControllerArgumentLocatorsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('stdClass', 'stdClass');
        $container->register(TestCase::class, 'stdClass');
        $container->register('c1', RemoveTestController1::class)->addTag('controller.service_arguments');
        $container->register('c2', RemoveTestController2::class)->addTag('controller.service_arguments')
            ->addMethodCall('setTestCase', [new Reference('c1')]);

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $controllers = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $getLocator = fn ($controllers, $k) => $container->getDefinition((string) $container->getDefinition((string) $controllers[$k]->getValues()[0])->getFactory()[0])->getArgument(0);

        $this->assertCount(2, $getLocator($controllers, 'c1::fooAction'));
        $this->assertCount(1, $getLocator($controllers, 'c2::setTestCase'));
        $this->assertCount(1, $getLocator($controllers, 'c2::fooAction'));

        (new ResolveInvalidReferencesPass())->process($container);

        $this->assertCount(1, $getLocator($controllers, 'c2::setTestCase'));
        $this->assertSame([], $getLocator($controllers, 'c2::fooAction'));

        (new RemoveEmptyControllerArgumentLocatorsPass())->process($container);

        $controllers = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $this->assertSame(['c1::fooAction', 'c1:fooAction'], array_keys($controllers));
        $this->assertSame(['bar'], array_keys($getLocator($controllers, 'c1::fooAction')));

        $expectedLog = [
            'Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass: Removing service-argument resolver for controller "c2::fooAction": no corresponding services exist for the referenced types.',
            'Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass: Removing method "setTestCase" of service "c2" from controller candidates: the method is called at instantiation, thus cannot be an action.',
        ];

        $this->assertEqualsCanonicalizing($expectedLog, $container->getCompiler()->getLog());
    }

    public function testInvoke()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('invokable', InvokableRegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        (new RegisterControllerArgumentLocatorsPass())->process($container);
        (new RemoveEmptyControllerArgumentLocatorsPass())->process($container);

        $this->assertEquals(
            ['invokable::__invoke', 'invokable:__invoke', 'invokable'],
            array_keys($container->getDefinition((string) $resolver->getArgument(0))->getArgument(0))
        );
    }
}

class RemoveTestController1
{
    public function fooAction(\stdClass $bar, ?ClassNotInContainer $baz = null)
    {
    }
}

class RemoveTestController2
{
    public function setTestCase(TestCase $test)
    {
    }

    public function fooAction(?ClassNotInContainer $bar = null)
    {
    }
}

class InvokableRegisterTestController
{
    public function __invoke(\stdClass $bar)
    {
    }
}

class ClassNotInContainer
{
}
