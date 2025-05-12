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
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * @group legacy
 */
class ServiceSubscriberTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        class_exists(LegacyTestService::class);
    }

    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = [
            LegacyTestService::class.'::aService' => Service2::class,
            LegacyTestService::class.'::nullableInAttribute' => '?'.Service2::class,
            LegacyTestService::class.'::nullableReturnType' => '?'.Service2::class,
            new SubscribedService(LegacyTestService::class.'::withAttribute', Service2::class, true, new Required()),
        ];

        $this->assertEquals($expected, LegacyChildTestService::getSubscribedServices());
    }

    public function testSetContainerIsCalledOnParent()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $this->assertSame($container, (new LegacyTestService())->setContainer($container));
    }

    public function testParentNotCalledIfHasMagicCall()
    {
        $container = new class([]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
        $service = new class extends LegacyParentWithMagicCall {
            use ServiceSubscriberTrait;

            private $container;
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
            use ServiceSubscriberTrait;

            private $container;
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

        $testService = new class extends LegacyParentTestService2 implements ServiceSubscriberInterface {
            use ServiceSubscriberTrait;
        };
        $this->assertNull($testService->setContainer($container1));
        $this->assertSame($container1, $testService->setContainer($container2));
    }
}
