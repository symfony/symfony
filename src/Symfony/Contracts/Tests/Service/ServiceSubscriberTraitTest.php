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

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Component1\Dir1\Service1;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\OtherDir\Component1\Dir2\Service2;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ServiceSubscriberTraitTest extends TestCase
{
    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [
            TestService::class.'::aService' => Service2::class,
            TestService::class.'::nullableService' => '?'.Service2::class,
            new SubscribedService(TestService::class.'::withAttribute', Service2::class, true, new Required()),
        ];

        $this->assertEquals($expected, ChildTestService::getSubscribedServices());
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
        $service = new class() extends ParentWithMagicCall {
            use ServiceSubscriberTrait;
        };

        $this->assertNull($service->setContainer($container));
        $this->assertSame([], $service::getSubscribedServices());
    }

    public function testParentNotCalledIfNoParent()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $service = new class() {
            use ServiceSubscriberTrait;
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
    use ServiceSubscriberTrait;

    #[SubscribedService]
    public function aService(): Service2
    {
    }

    #[SubscribedService]
    public function nullableService(): ?Service2
    {
    }

    #[SubscribedService(attributes: new Required())]
    public function withAttribute(): ?Service2
    {
    }
}

class ChildTestService extends TestService
{
    #[SubscribedService]
    public function aChildService(): Service3
    {
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

class Service3
{
}

class ParentTestService2
{
    /** @var ContainerInterface */
    protected $container;

    public function setContainer(ContainerInterface $container)
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }
}

class TestService2 extends ParentTestService2 implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;
}
