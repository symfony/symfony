<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberTrait;

class ServiceSubscriberTraitTest extends TestCase
{
    public function testMethodsOnParentsAndChildrenAreIgnoredInGetSubscribedServices()
    {
        $expected = array(TestService::class.'::aService' => '?Symfony\Component\DependencyInjection\Tests\Service2');

        $this->assertEquals($expected, ChildTestService::getSubscribedServices());
    }

    public function testSetContainerIsCalledOnParent()
    {
        $container = new Container();

        $this->assertSame($container, (new TestService())->setContainer($container));
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
