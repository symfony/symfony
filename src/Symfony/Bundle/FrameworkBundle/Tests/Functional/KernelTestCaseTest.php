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

class KernelTestCaseTest extends AbstractWebTestCase
{
    public function testThatPrivateServicesAreUnavailableIfTestConfigIsDisabled()
    {
        self::bootKernel(['test_case' => 'TestServiceContainer', 'root_config' => 'test_disabled.yml', 'environment' => 'test_disabled']);

        self::expectException(\LogicException::class);
        self::getContainer();
    }

    public function testThatPrivateServicesAreAvailableIfTestConfigIsEnabled()
    {
        self::bootKernel(['test_case' => 'TestServiceContainer']);

        $container = self::getContainer();
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(TestContainer::class, $container);
        self::assertTrue($container->has(PublicService::class));
        self::assertTrue($container->has(NonPublicService::class));
        self::assertTrue($container->has(PrivateService::class));
        self::assertTrue($container->has('private_service'));
        self::assertFalse($container->has(UnusedPrivateService::class));
    }
}
