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
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Symfony\Contracts\Tests\Fixtures\TestServiceSubscriberUnion;

class ServiceSubscriberTraitTest extends TestCase
{
    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [TestService::class.'::aService' => '?Symfony\Contracts\Tests\Service\Service2'];

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

    /**
     * @return ?ContainerInterface
     */
    public function setContainer(ContainerInterface $container)
    {
        return $container;
    }
}

class TestService extends ParentTestService implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function aService(): Service2
    {
    }
}

class ChildTestService extends TestService
{
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
