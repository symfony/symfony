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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;

class RegisterControllerArgumentLocatorsPassTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Class "Symfony\Component\HttpKernel\Tests\DependencyInjection\NotFound" used for service "foo" cannot be found.
     */
    public function testInvalidClass()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', NotFound::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing "action" attribute on tag "controller.service_arguments" {"argument":"bar"} for service "foo".
     */
    public function testNoAction()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('argument' => 'bar'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing "argument" attribute on tag "controller.service_arguments" {"action":"fooAction"} for service "foo".
     */
    public function testNoArgument()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'fooAction'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing "id" attribute on tag "controller.service_arguments" {"action":"fooAction","argument":"bar"} for service "foo".
     */
    public function testNoService()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'fooAction', 'argument' => 'bar'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid "action" attribute on tag "controller.service_arguments" for service "foo": no public "barAction()" method found on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".
     */
    public function testInvalidMethod()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'barAction', 'argument' => 'bar', 'id' => 'bar_service'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid "controller.service_arguments" tag for service "foo": method "fooAction()" has no "baz" argument on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".
     */
    public function testInvalidArgument()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'fooAction', 'argument' => 'baz', 'id' => 'bar'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testAllActions()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $expected = new Definition(ServiceLocator::class);
        $expected->addArgument(array('foo:fooAction' => new ServiceClosureArgument(new Reference('arguments.foo:fooAction'))));
        $expected->addTag('container.service_locator');
        $this->assertEquals($expected, $container->getDefinition('argument_resolver.service')->getArgument(0));

        $locator = $container->getDefinition('arguments.foo:fooAction');
        $this->assertSame(ServiceLocator::class, $locator->getClass());
        $this->assertFalse($locator->isPublic());
        $this->assertTrue($locator->isAutowired());

        $expected = array('bar' => new ServiceClosureArgument(new TypedReference('stdClass', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, false)));
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testExplicitArgument()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'fooAction', 'argument' => 'bar', 'id' => 'bar'))
            ->addTag('controller.service_arguments', array('action' => 'fooAction', 'argument' => 'bar', 'id' => 'baz')) // should be ignored, the first wins
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition('arguments.foo:fooAction');
        $this->assertFalse($locator->isAutowired());

        $expected = array('bar' => new ServiceClosureArgument(new TypedReference('bar', 'stdClass', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false)));
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testOptionalArgument()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument(array());

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', array('action' => 'fooAction', 'argument' => 'bar', 'id' => '?bar'))
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition('arguments.foo:fooAction');

        $expected = array('bar' => new ServiceClosureArgument(new TypedReference('bar', 'stdClass', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, false)));
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testSameIdClass()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument(array());

        $container->register(RegisterTestController::class, RegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $expected = array(
            RegisterTestController::class.':fooAction' => new ServiceClosureArgument(new Reference('arguments.'.RegisterTestController::class.':fooAction')),
            RegisterTestController::class.'::fooAction' => new ServiceClosureArgument(new Reference('arguments.'.RegisterTestController::class.':fooAction')),
        );
        $this->assertEquals($expected, $resolver->getArgument(0)->getArgument(0));
    }
}

class RegisterTestController
{
    public function __construct(\stdClass $bar)
    {
    }

    public function fooAction(\stdClass $bar)
    {
    }

    protected function barAction(\stdClass $bar)
    {
    }
}
