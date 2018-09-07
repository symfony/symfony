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

class TestServiceContainerTest extends WebTestCase
{
    public function testThatPrivateServicesAreUnavailableIfTestConfigIsDisabled()
    {
        static::bootKernel(array('test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled'));

        $this->assertInstanceOf(ContainerInterface::class, static::$container);
        $this->assertNotInstanceOf(TestContainer::class, static::$container);
        $this->assertTrue(static::$container->has(PublicService::class));
        $this->assertFalse(static::$container->has(NonPublicService::class));
        $this->assertFalse(static::$container->has(PrivateService::class));
        $this->assertFalse(static::$container->has('private_service'));
        $this->assertFalse(static::$container->has(UnusedPrivateService::class));
    }

    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        static::bootKernel(array('test_case' => 'TestServiceContainer'));

        $this->assertInstanceOf(TestContainer::class, static::$container);
        $this->assertTrue(static::$container->has(PublicService::class));
        $this->assertTrue(static::$container->has(NonPublicService::class));
        $this->assertTrue(static::$container->has(PrivateService::class));
        $this->assertTrue(static::$container->has('private_service'));
        $this->assertFalse(static::$container->has(UnusedPrivateService::class));
    }
}
