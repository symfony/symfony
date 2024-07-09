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
use Symfony\Component\DependencyInjection\Argument\LazyClosure;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterControllerArgumentLocatorsPass;
use Symfony\Component\HttpKernel\Tests\Fixtures\DataCollector\DummyController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Suit;

class RegisterControllerArgumentLocatorsPassTest extends TestCase
{
    public function testInvalidClass()
    {
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $this->expectException(InvalidArgumentException::class);
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
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

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
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

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
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine controller argument for "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassController::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClass". Did you forget to add a use statement?');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $error = $container->getDefinition('argument_resolver.service')->getArgument(0);
        $error = $container->getDefinition($error)->getArgument(0)['foo::fooAction']->getValues()[0];
        $error = $container->getDefinition($error)->getArgument(0)['nonExistent']->getValues()[0];

        $container->get($error);
    }

    public function testExceptionOnNonExistentTypeHintDifferentNamespace()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine controller argument for "Symfony\Component\HttpKernel\Tests\DependencyInjection\NonExistentClassDifferentNamespaceController::fooAction()": the $nonExistent argument is type-hinted with the non-existent class or interface: "Acme\NonExistentClass".');
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonExistentClassDifferentNamespaceController::class)
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $error = $container->getDefinition('argument_resolver.service')->getArgument(0);
        $error = $container->getDefinition($error)->getArgument(0)['foo::fooAction']->getValues()[0];
        $error = $container->getDefinition($error)->getArgument(0)['nonExistent']->getValues()[0];

        $container->get($error);
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

        $this->assertEqualsCanonicalizing(['foo::barAction', 'foo::fooAction'], array_keys($locator));
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
            ->addTag('controller.service_arguments');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertTrue($container->getDefinition('foo')->isPublic());
    }

    public function testControllersAreMadeNonLazy()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', DummyController::class)
            ->addTag('controller.service_arguments')
            ->setLazy(true);

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertFalse($container->getDefinition('foo')->isLazy());
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
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

        $expected = ['bar' => new ServiceClosureArgument(new Reference('foo'))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public static function provideBindings()
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
        $resolver = $container->register('argument_resolver.service', 'stdClass')->addArgument([]);

        $container->register('foo', ArgumentWithoutTypeController::class)
            ->setBindings([$bindingKey => '%foo%'])
            ->addTag('controller.service_arguments');

        $container->setParameter('foo', 'foo_val');

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        $locator = $container->get($locatorId);
        $this->assertSame('foo_val', $locator->get('foo::fooAction')->get('someArg'));
    }

    public static function provideBindScalarValueToControllerArgument()
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

        $locator = $container->getDefinition((string) $locator['child::fooAction']->getValues()[0]);
        $locator = $container->getDefinition((string) $locator->getFactory()[0])->getArgument(0);
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

    public function testAlias()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments');

        $container->setAlias(RegisterTestController::class, 'foo')->setPublic(true);

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEqualsCanonicalizing([RegisterTestController::class.'::fooAction', 'foo::fooAction'], array_keys($locator));
    }

    public function testEnumArgumentIsIgnored()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register('foo', NonNullableEnumArgumentWithDefaultController::class)
            ->addTag('controller.service_arguments')
        ;

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEmpty(array_keys($locator), 'enum typed argument is ignored');
    }

    public function testBindWithTarget()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service')->addArgument([]);

        $container->register(ControllerDummy::class, 'bar');
        $container->register(ControllerDummy::class.' $imageStorage', 'baz');

        $container->register('foo', WithTarget::class)
            ->setBindings(['string $someApiKey' => new Reference('the_api_key')])
            ->addTag('controller.service_arguments');

        (new RegisterControllerArgumentLocatorsPass())->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $locator['foo::fooAction']->getValues()[0]);
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

        $expected = [
            'apiKey' => new ServiceClosureArgument(new Reference('the_api_key')),
            'service1' => new ServiceClosureArgument(new TypedReference(ControllerDummy::class, ControllerDummy::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'imageStorage')),
            'service2' => new ServiceClosureArgument(new TypedReference(ControllerDummy::class, ControllerDummy::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'service2')),
        ];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testResponseArgumentIsIgnored()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service', 'stdClass')->addArgument([]);

        $container->register('foo', WithResponseArgument::class)
            ->addTag('controller.service_arguments');

        (new RegisterControllerArgumentLocatorsPass())->process($container);

        $locator = $container->getDefinition((string) $resolver->getArgument(0))->getArgument(0);
        $this->assertEmpty(array_keys($locator), 'Response typed argument is ignored');
    }

    public function testAutowireAttribute()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('argument_resolver.service', 'stdClass')->addArgument([]);

        $container->register('some.id', \stdClass::class)->setPublic(true);
        $container->setParameter('some.parameter', 'foo');

        $container->register('foo', WithAutowireAttribute::class)
            ->addTag('controller.service_arguments');

        (new RegisterControllerArgumentLocatorsPass())->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        $locator = $container->get($locatorId)->get('foo::fooAction');

        $this->assertCount(9, $locator->getProvidedServices());
        $this->assertInstanceOf(\stdClass::class, $locator->get('service1'));
        $this->assertSame('foo/bar', $locator->get('value'));
        $this->assertSame('foo', $locator->get('expression'));
        $this->assertInstanceOf(\stdClass::class, $locator->get('serviceAsValue'));
        $this->assertInstanceOf(\stdClass::class, $locator->get('expressionAsValue'));
        $this->assertSame('bar', $locator->get('rawValue'));
        $this->assertSame('@bar', $locator->get('escapedRawValue'));
        $this->assertSame('foo', $locator->get('customAutowire'));
        $this->assertInstanceOf(FooInterface::class, $autowireCallable = $locator->get('autowireCallable'));
        $this->assertInstanceOf(LazyClosure::class, $autowireCallable);
        $this->assertInstanceOf(\stdClass::class, $autowireCallable->service);
        $this->assertFalse($locator->has('service2'));
    }

    /**
     * @group legacy
     */
    public function testTaggedIteratorAndTaggedLocatorAttributes()
    {
        $container = new ContainerBuilder();
        $container->setParameter('some.parameter', 'bar');
        $resolver = $container->register('argument_resolver.service', \stdClass::class)->addArgument([]);

        $container->register('bar', \stdClass::class)->addTag('foobar');
        $container->register('baz', \stdClass::class)->addTag('foobar');

        $container->register('foo', WithTaggedIteratorAndTaggedLocator::class)
            ->addTag('controller.service_arguments');

        (new RegisterControllerArgumentLocatorsPass())->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        /** @var ServiceLocator $locator */
        $locator = $container->get($locatorId)->get('foo::fooAction');

        $this->assertCount(2, $locator->getProvidedServices());

        $this->assertTrue($locator->has('iterator1'));
        $this->assertInstanceOf(RewindableGenerator::class, $argIterator = $locator->get('iterator1'));
        $this->assertCount(2, $argIterator);

        $this->assertTrue($locator->has('locator1'));
        $this->assertInstanceOf(ServiceLocator::class, $argLocator = $locator->get('locator1'));
        $this->assertCount(2, $argLocator);
        $this->assertTrue($argLocator->has('bar'));
        $this->assertTrue($argLocator->has('baz'));

        $this->assertSame(iterator_to_array($argIterator), [$argLocator->get('bar'), $argLocator->get('baz')]);
    }

    public function testAutowireIteratorAndAutowireLocatorAttributes()
    {
        $container = new ContainerBuilder();
        $container->setParameter('some.parameter', 'bar');
        $resolver = $container->register('argument_resolver.service', \stdClass::class)->addArgument([]);

        $container->register('bar', \stdClass::class)->addTag('foobar');
        $container->register('baz', \stdClass::class)->addTag('foobar');

        $container->register('foo', WithAutowireIteratorAndAutowireLocator::class)
            ->addTag('controller.service_arguments');

        (new RegisterControllerArgumentLocatorsPass())->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        /** @var ServiceLocator $locator */
        $locator = $container->get($locatorId)->get('foo::fooAction');

        $this->assertCount(4, $locator->getProvidedServices());

        $this->assertTrue($locator->has('iterator1'));
        $this->assertInstanceOf(RewindableGenerator::class, $argIterator = $locator->get('iterator1'));
        $this->assertCount(2, $argIterator);

        $this->assertTrue($locator->has('locator1'));
        $this->assertInstanceOf(ServiceLocator::class, $argLocator = $locator->get('locator1'));
        $this->assertCount(2, $argLocator);
        $this->assertTrue($argLocator->has('bar'));
        $this->assertTrue($argLocator->has('baz'));

        $this->assertSame(iterator_to_array($argIterator), [$argLocator->get('bar'), $argLocator->get('baz')]);

        $this->assertTrue($locator->has('container1'));
        $this->assertInstanceOf(ServiceLocator::class, $argLocator = $locator->get('container1'));
        $this->assertCount(2, $argLocator);
        $this->assertTrue($argLocator->has('bar'));
        $this->assertTrue($argLocator->has('baz'));

        $this->assertTrue($locator->has('container2'));
        $this->assertInstanceOf(ServiceLocator::class, $argLocator = $locator->get('container2'));
        $this->assertCount(1, $argLocator);
        $this->assertTrue($argLocator->has('foo'));
        $this->assertSame('bar', $argLocator->get('foo'));
    }

    public function testTaggedControllersAreRegisteredInControllerResolver()
    {
        $container = new ContainerBuilder();
        $container->register('argument_resolver.service')->addArgument([]);
        $controllerResolver = $container->register('controller_resolver');

        $container->register('foo', RegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        // duplicates should be removed
        $container->register('bar', RegisterTestController::class)
            ->addTag('controller.service_arguments')
        ;

        // services with no tag should be ignored
        $container->register('baz', ControllerDummy::class);

        $pass = new RegisterControllerArgumentLocatorsPass();
        $pass->process($container);

        $this->assertSame([['allowControllers', [[RegisterTestController::class]]]], $controllerResolver->getMethodCalls());
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

class ContainerAwareRegisterTestController
{
    protected ?ContainerInterface $container;

    public function setContainer(?ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

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
    public function fooAction(?NonExistentClass $nonExistent = null)
    {
    }

    public function barAction(?NonExistentClass $nonExistent, $bar)
    {
    }
}

class ArgumentWithoutTypeController
{
    public function fooAction(string $someArg)
    {
    }
}

class NonNullableEnumArgumentWithDefaultController
{
    public function fooAction(Suit $suit = Suit::Spades)
    {
    }
}

class WithTarget
{
    public function fooAction(
        #[Target('some.api.key')]
        string $apiKey,
        #[Target('image.storage')]
        ControllerDummy $service1,
        ControllerDummy $service2,
    ) {
    }
}

class WithResponseArgument
{
    public function fooAction(Response $response, ?Response $nullableResponse)
    {
    }
}

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class CustomAutowire extends Autowire
{
    public function __construct(string $parameter)
    {
        parent::__construct('%'.$parameter.'%');
    }
}

interface FooInterface
{
    public function foo();
}

class WithAutowireAttribute
{
    public function fooAction(
        #[Autowire(service: 'some.id')]
        \stdClass $service1,
        #[Autowire(value: '%some.parameter%/bar')]
        string $value,
        #[Autowire(expression: "parameter('some.parameter')")]
        string $expression,
        #[Autowire('@some.id')]
        \stdClass $serviceAsValue,
        #[Autowire("@=service('some.id')")]
        \stdClass $expressionAsValue,
        #[Autowire('bar')]
        string $rawValue,
        #[Autowire('@@bar')]
        string $escapedRawValue,
        #[CustomAutowire('some.parameter')]
        string $customAutowire,
        #[AutowireCallable(service: 'some.id', method: 'bar')]
        FooInterface $autowireCallable,
        #[Autowire(service: 'invalid.id')]
        ?\stdClass $service2 = null,
    ) {
    }
}

class WithTaggedIteratorAndTaggedLocator
{
    public function fooAction(
        #[TaggedIterator('foobar')] iterable $iterator1,
        #[TaggedLocator('foobar')] ServiceLocator $locator1,
    ) {
    }
}

class WithAutowireIteratorAndAutowireLocator
{
    public function fooAction(
        #[AutowireIterator('foobar')] iterable $iterator1,
        #[AutowireLocator('foobar')] ServiceLocator $locator1,
        #[AutowireLocator(['bar', 'baz'])] ContainerInterface $container1,
        #[AutowireLocator(['foo' => new Autowire('%some.parameter%')])] ContainerInterface $container2,
    ) {
    }
}
