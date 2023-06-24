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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class TestServiceContainerTest extends AbstractWebTestCase
{
    public function testLogicExceptionIfTestConfigIsDisabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);

        $this->expectException(\LogicException::class);

        static::getContainer();
    }

    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $this->assertInstanceOf(TestContainer::class, static::getContainer());
        $this->assertTrue(static::getContainer()->has(PublicService::class));
        $this->assertTrue(static::getContainer()->has(NonPublicService::class));
        $this->assertTrue(static::getContainer()->has(PrivateService::class));
        $this->assertTrue(static::getContainer()->has('private_service'));
        $this->assertFalse(static::getContainer()->has(UnusedPrivateService::class));
    }

    public function testThatPrivateServicesCanBeSetIfTestConfigIsEnabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $service = new \stdClass();

        $container->set('private_service', $service);
        $this->assertSame($service, $container->get('private_service'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "private_service" service is already initialized, you cannot replace it.');
        $container->set('private_service', new \stdClass());
    }

    public function testSetDecoratedService()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();

        $service = new PrivateService();
        $container->set('decorated', $service);
        $this->assertSame($service, $container->get('decorated')->inner);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testBootKernel()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);
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
