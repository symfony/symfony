<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Tests\Service;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceMethodsSubscriberTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Tests\Service\Fixtures\HookedPropertyService;
use Symfony\Contracts\Tests\Service\Fixtures\MyDependency;
use Symfony\Contracts\Tests\Service\Fixtures\NonHookedPropertyService;
use Symfony\Contracts\Tests\Service\Fixtures\WriteOnlyPropertyService;

class ServiceMethodsSubscriberTraitTest extends TestCase
{
    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [
            TestService::class.'::aService' => Service2::class,
            TestService::class.'::nullableInAttribute' => '?'.Service2::class,
            TestService::class.'::nullableReturnType' => '?'.Service2::class,
            new SubscribedService(TestService::class.'::withAttribute', Service2::class, true, new Required()),
        ];

        $this->assertEquals($expected, ChildTestService::getSubscribedServices());
    }

    #[RequiresPhp('>= 8.4')]
    public function testHookedProperties()
    {
        $this->assertSame([
            HookedPropertyService::class.'::$myDependency::get' => MyDependency::class,
            HookedPropertyService::class.'::$myNullableDependency::get' => '?'.MyDependency::class,
            HookedPropertyService::class.'::$myTentativeDependency::get' => '?'.MyDependency::class,
            HookedPropertyService::class.'::$myCachedDependency::get' => MyDependency::class,
            'my_key' => MyDependency::class,
        ], HookedPropertyService::getSubscribedServices());

        $container = new class([HookedPropertyService::class.'::$myDependency::get' => static fn () => new MyDependency()]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $service = new HookedPropertyService();
        $service->setContainer($container);

        $this->assertInstanceOf(MyDependency::class, $service->myDependency);
    }

    public function testPropertyHookRequired()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot use "Symfony\Contracts\Service\Attribute\SubscribedService" on property "Symfony\Contracts\Tests\Service\Fixtures\NonHookedPropertyService::$myDependency" (can only be used on properties with a get hook).');
        NonHookedPropertyService::getSubscribedServices();
    }

    #[RequiresPhp('>= 8.4')]
    public function testPropertyWithGetHookRequired()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot use "Symfony\Contracts\Service\Attribute\SubscribedService" on property "Symfony\Contracts\Tests\Service\Fixtures\WriteOnlyPropertyService::$myDependency" (can only be used on properties with a get hook).');
        WriteOnlyPropertyService::getSubscribedServices();
    }

    public function testSetContainerIsCalledOnParent()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $this->assertSame($container, (new TestService())->setContainer($container));
    }

    public function testParentNotCalledIfHasMagicCall()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $service = new class extends ParentWithMagicCall {
            use ServiceMethodsSubscriberTrait;
        };

        $this->assertNull($service->setContainer($container));
        $this->assertSame([], $service::getSubscribedServices());
    }

    public function testParentNotCalledIfNoParent()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $service = new class {
            use ServiceMethodsSubscriberTrait;
        };

        $this->assertNull($service->setContainer($container));
        $this->assertSame([], $service::getSubscribedServices());
    }

    public function testSetContainerCalledFirstOnParent()
    {
        $container1 = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $container2 = clone $container1;

        $testService = new TestService2();
        $this->assertNull($testService->setContainer($container1));
        $this->assertSame($container1, $testService->setContainer($container2));
    }
}

class ParentTestService
{
    public function aParentService(): Service1
    {
    }

    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        return $container;
    }
}

class TestService extends ParentTestService implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;

    protected ContainerInterface $container;

    #[SubscribedService]
    public function aService(): Service2
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService(nullable: true)]
    public function nullableInAttribute(): Service2
    {
        if (!$this->container->has(__METHOD__)) {
            throw new \LogicException();
        }

        return $this->container->get(__METHOD__);
    }

    #[SubscribedService]
    public function nullableReturnType(): ?Service2
    {
        return $this->container->get(__METHOD__);
    }

    #[SubscribedService(attributes: new Required())]
    public function withAttribute(): ?Service2
    {
        return $this->container->get(__METHOD__);
    }
}

class ChildTestService extends TestService
{
    #[SubscribedService]
    public function aChildService(): Service3
    {
        return $this->container->get(__METHOD__);
    }
}

class ParentWithMagicCall
{
    public function __call($method, $args)
    {
        throw new \BadMethodCallException('Should not be called.');
    }

    public static function __callStatic($method, $args)
    {
        throw new \BadMethodCallException('Should not be called.');
    }
}

class Service1
{
}

class Service2
{
}

class Service3
{
}

class ParentTestService2
{
    protected ContainerInterface $container;

    public function setContainer(ContainerInterface $container)
    {
        $previous = $this->container ?? null;
        $this->container = $container;

        return $previous;
    }
}

class TestService2 extends ParentTestService2 implements ServiceSubscriberInterface
{
    use ServiceMethodsSubscriberTrait;
}
