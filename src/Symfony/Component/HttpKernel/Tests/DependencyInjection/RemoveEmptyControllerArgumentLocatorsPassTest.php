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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
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
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('stdClass', 'stdClass');
        $container->register(parent::class, 'stdClass');
        $container->register('c1', RemoveTestController1::class)->addTag('controller.service_arguments');
        $container->register('c2', RemoveTestController2::class)->addTag('controller.service_arguments')
            ->addMethodCall('setTestCase', array(new Reference('c1')));

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertCount(2, $container->getDefinition('arguments.c1:fooAction')->getArgument(0));
        $this->assertCount(1, $container->getDefinition('arguments.c2:setTestCase')->getArgument(0));
        $this->assertCount(1, $container->getDefinition('arguments.c2:fooAction')->getArgument(0));

        $pass = new ResolveInvalidReferencesPass();
        $pass->process($container);

        $this->assertCount(1, $container->getDefinition('arguments.c2:setTestCase')->getArgument(0));
        $this->assertSame(array(), $container->getDefinition('arguments.c2:fooAction')->getArgument(0));

        $pass = new RemoveEmptyControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition('arguments.c2:setTestCase'));
        $this->assertFalse($container->hasDefinition('arguments.c2:fooAction'));

        $this->assertCount(1, $container->getDefinition('arguments.c1:fooAction')->getArgument(0));
        $this->assertArrayHasKey('bar', $container->getDefinition('arguments.c1:fooAction')->getArgument(0));

        $expectedLog = array(
            'Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass: Removing method "setTestCase" of service "c2" from controller candidates: the method is called at instantiation, thus cannot be an action.',
            'Symfony\Component\HttpKernel\DependencyInjection\RemoveEmptyControllerArgumentLocatorsPass: Removing service-argument-resolver for controller "c2:fooAction": no corresponding definitions were found for the referenced services/types. Did you forget to enable autowiring?',
        );

        $this->assertSame($expectedLog, $container->getCompiler()->getLog());

        $this->assertEquals(array('c1:fooAction' => new ServiceClosureArgument(new Reference('arguments.c1:fooAction'))), $container->getDefinition('argument_resolver.service')->getArgument(0)->getArgument(0));
    }
}

class RemoveTestController1
{
    public function fooAction(\stdClass $bar, NotFound $baz)
    {
    }
}

class RemoveTestController2
{
    public function setTestCase(TestCase $test)
    {
    }

    public function fooAction(NotFound $bar)
    {
    }
}
