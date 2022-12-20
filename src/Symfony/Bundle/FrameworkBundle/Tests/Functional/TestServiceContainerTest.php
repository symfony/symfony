<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\NonPublicService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\PrivateService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\PublicService;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestServiceContainer\UnusedPrivateService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestServiceContainerTest extends AbstractWebTestCase
{
    /**
     * @group legacy
     */
    public function testThatPrivateServicesAreUnavailableIfTestConfigIsDisabled()
    {
        self::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);

        self::assertInstanceOf(ContainerInterface::class, static::$container);
        self::assertNotInstanceOf(TestContainer::class, static::$container);
        self::assertTrue(static::$container->has(PublicService::class));
        self::assertFalse(static::$container->has(NonPublicService::class));
        self::assertFalse(static::$container->has(PrivateService::class));
        self::assertFalse(static::$container->has('private_service'));
        self::assertFalse(static::$container->has(UnusedPrivateService::class));
    }

    /**
     * @group legacy
     */
    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        self::bootKernel(['test_case' => 'TestServiceContainer']);

        self::assertInstanceOf(TestContainer::class, static::$container);
        self::assertTrue(static::$container->has(PublicService::class));
        self::assertTrue(static::$container->has(NonPublicService::class));
        self::assertTrue(static::$container->has(PrivateService::class));
        self::assertTrue(static::$container->has('private_service'));
        self::assertFalse(static::$container->has(UnusedPrivateService::class));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testBootKernel()
    {
        self::bootKernel(['test_case' => 'TestServiceContainer']);
    }

    /**
     * @depends testBootKernel
     */
    public function testKernelIsNotInitialized()
    {
        self::assertNull(self::$class);
        self::assertNull(self::$kernel);
        self::assertFalse(self::$booted);
    }
}
