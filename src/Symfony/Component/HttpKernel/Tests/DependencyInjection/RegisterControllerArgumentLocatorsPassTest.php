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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;

class RegisterControllerArgumentLocatorsPassTest extends TestCase
{
    public function testInvalidClass()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Class "Symfony\Component\HttpKernel\Tests\DependencyInjection\NotFound" used for service "foo" cannot be found.');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NotFound::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoAction()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Missing "action" attribute on tag "controller.service_arguments" {"argument":"bar"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['argument' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoArgument()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Missing "argument" attribute on tag "controller.service_arguments" {"action":"fooAction"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoService()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Missing "id" attribute on tag "controller.service_arguments" {"action":"fooAction","argument":"bar"} for service "foo".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testInvalidMethod()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid "action" attribute on tag "controller.service_arguments" for service "foo": no public "barAction()" method found on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'barAction', 'argument' => 'bar', 'id' => 'bar_service'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testInvalidArgument()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Invalid "controller.service_arguments" tag for service "foo": method "fooAction()" has no "baz" argument on class "Symfony\Component\HttpKernel\Tests\DependencyInjection\RegisterTestController".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'baz', 'id' => 'bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testAllActions()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $this->assertEquals(['foo::fooAction'], array_keys($locator));
        $this->assertInstanceof(ServiceClosureArgument::class, $locator['foo::fooAction']);

        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);

        $this->assertSame(ServiceLocator::class, $locator->getClass());
        $this->assertFalse($locator->isPublic());

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference(ControllerDummy::class, ControllerDummy::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'bar'))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testExplicitArgument()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => 'bar'])
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => 'baz']) // should be ignored, the first wins
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference('bar', ControllerDummy::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testOptionalArgument()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments', ['action' => 'fooAction', 'argument' => 'bar', 'id' => '?bar'])
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new TypedReference('bar', ControllerDummy::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testSkipSetContainer()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ContainerAwareRegisterTestController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertSame(['foo::fooAction'], array_keys($locator));
    }

    public function testExceptionOnNonExistentTypeHint()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Cannot determine controller argument for "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassController::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClass". Did you forget to add a use statement?');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testExceptionOnNonExistentTypeHintDifferentNamespace()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Cannot determine controller argument for "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassDifferentNamespaceController::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Acme\NonExistentClass".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassDifferentNamespaceController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);
    }

    public function testNoExceptionOnNonExistentTypeHintOptionalArg()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassOptionalController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertSame(['foo::barAction', 'foo::fooAction'], array_keys($locator));
    }

    public function testArgumentWithNoTypeHintIsOk()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEmpty(array_keys($locator));
    }

    public function testControllersAreMadePublic()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->setPublic(false)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertTrue($container->getDefinition('foo')->isPublic());
    }

    /**
     * @dataProvider provideBindings
     */
    public function testBindings($bindingName)
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->setBindings([$bindingName => new Reference('foo')])
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new Reference('foo'))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function provideBindings()
    {
        return [
            [ControllerDummy::class.'$bar'],
            [ControllerDummy::class],
            ['$bar'],
        ];
    }

    /**
     * @dataProvider provideBindScalarValueToControllerArgument
     */
    public function testBindScalarValueToControllerArgument($bindingKey)
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->setBindings([$bindingKey => '%foo%'])
            ->addTag('controller.service_arguments');

        $container->setParameter('foo', 'foo_val');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);

        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);

        // assert the locator has a someArg key
        $arguments = $locator->getArgument(0);
        $this->assertArrayHasKey('someArg', $arguments);
        $this->assertInstanceOf(ServiceClosureArgument::class, $arguments['someArg']);
        // get the Reference that someArg points to
        $reference = $arguments['someArg']->getValues()[0];
        // make sure this service *does* exist and returns the correct value
        $this->assertTrue($container->has((string) $reference));
        $this->assertSame('foo_val', $container->get((string) $reference));
    }

    public function provideBindScalarValueToControllerArgument()
    {
        yield ['$someArg'];
        yield ['string $someArg'];
    }

    public function testBindingsOnChildDefinitions()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('parent', ArgumentWithoutTypeController::class);

        $container->setDefinition('child', (new ChildDefinition('parent'))
            ->setBindings(['$someArg' => new Reference('parent')])
            ->addTag('controller.service_arguments')
        );

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertInstanceOf(ServiceClosureArgument::class, $locator['child::fooAction']);

        $locator = $container->getDefinition((string) $locator['child::fooAction']->getValues()[0])->getArgument(0);
        $this->assertInstanceOf(ServiceClosureArgument::class, $locator['someArg']);
        $this->assertEquals(new Reference('parent'), $locator['someArg']->getValues()[0]);
    }

    public function testNotTaggedControllerServiceReceivesLocatorArgument()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.not_tagged_controller')->addArgument([]);

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locatorArgument = $container->getDefinition('argument_resolver.not_tagged_controller')->getArgument(0);

        $this->assertInstanceOf(Reference::class, $locatorArgument);
    }
}

class RegisterTestController
{
    public function __construct(ControllerDummy $bar)
    {
    }

    public function fooAction(ControllerDummy $bar)
    {
    }

    protected function barAction(ControllerDummy $bar)
    {
    }
}

class ContainerAwareRegisterTestController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function fooAction(ControllerDummy $bar)
    {
    }
}

class ControllerDummy
{
}

class NonExistentClassController
{
    public function fooAction(NonExistentClass $nonExistent)
    {
    }
}

class NonExistentClassDifferentNamespaceController
{
    public function fooAction(\Acme\NonExistentClass $nonExistent)
    {
    }
}

class NonExistentClassOptionalController
{
    public function fooAction(NonExistentClass $nonExistent = null)
    {
    }

    public function barAction(NonExistentClass $nonExistent = null, $bar)
    {
    }
}

class ArgumentWithoutTypeController
{
    public function fooAction(string $someArg)
    {
    }
}
