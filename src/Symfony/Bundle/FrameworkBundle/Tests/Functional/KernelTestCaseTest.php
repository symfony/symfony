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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class KernelTestCaseTest extends AbstractWebTestCase
{
    public function testThatPrivateServicesAreUnavailableIfTestConfigIsDisabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);

        $this->expectException(\LogicException::class);
        static::getContainer();
    }

    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        static::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = static::getContainer();
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertInstanceOf(TestContainer::class, $container);
        $this->assertTrue($container->has(PublicService::class));
        $this->assertTrue($container->has(NonPublicService::class));
        $this->assertTrue($container->has(PrivateService::class));
        $this->assertTrue($container->has('private_service'));
        $this->assertFalse($container->has(UnusedPrivateService::class));
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
}
