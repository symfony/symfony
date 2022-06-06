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
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Symfony\Contracts\Tests\Fixtures\TestServiceSubscriberUnion;

class ServiceSubscriberTraitTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [LegacyTestService::class.'::aService' => '?'.Service2::class];

        $this->assertEquals($expected, LegacyChildTestService::getSubscribedServices());
    }

    /**
     * @requires PHP 8
     */
    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [
            TestService::class.'::aService' => Service2::class,
            TestService::class.'::nullableService' => '?'.Service2::class,
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

    /**
     * @requires PHP 8
     * @group legacy
     */
    public function testMethodsWithUnionReturnTypesAreIgnored()
    {
        $expected = [TestServiceSubscriberUnion::class.'::method1' => '?Symfony\Contracts\Tests\Fixtures\Service1'];

        $this->assertEquals($expected, TestServiceSubscriberUnion::getSubscribedServices());
    }
}

class ParentTestService
{
    public function aParentService(): Service1
    {
    }

    public function setContainer(ContainerInterface $container)
    {
        return $container;
    }
}

class LegacyTestService extends ParentTestService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function aService(): Service2
    {
    }
}

class LegacyChildTestService extends LegacyTestService
{
    public function aChildService(): Service3
    {
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
