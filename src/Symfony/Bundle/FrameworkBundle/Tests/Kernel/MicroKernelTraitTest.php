<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\HttpFoundation\Request;

class MicroKernelTraitTest extends TestCase
{
    /**
     * @group legacy
     * @expectedDeprecation Adding routes via the "Symfony\Bundle\FrameworkBundle\Tests\Kernel\MicroKernelWithConfigureRoutes:configureRoutes()" method is deprecated since Symfony 5.1 and will have no effect in 6.0; use "configureRouting()" instead.
     * @expectedDeprecation Not overriding the "Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait::configureRouting()" method is deprecated since Symfony 5.1 and will trigger a fatal error in 6.0.
     */
    public function testConfigureRoutingDeprecated()
    {
        $kernel = new MicroKernelWithConfigureRoutes('test', false);
        $kernel->boot();
        $kernel->handle(Request::create('/'));
    }

    public function test()
    {
        $kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('halloween', $response->getContent());
        $this->assertEquals('Have a great day!', $kernel->getContainer()->getParameter('halloween'));
        $this->assertInstanceOf('stdClass', $kernel->getContainer()->get('halloween'));
    }

    public function testAsEventSubscriber()
    {
        $kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/danger');
        $response = $kernel->handle($request);

        $this->assertSame('It\'s dangerous to go alone. Take this ⚔', $response->getContent());
    }

    public function testRoutingRouteLoaderTagIsAdded()
    {
        $frameworkExtension = $this->createMock(ExtensionInterface::class);
        $frameworkExtension
            ->expects($this->atLeastOnce())
            ->method('getAlias')
            ->willReturn('framework');
        $container = new ContainerBuilder();
        $container->registerExtension($frameworkExtension);
        $kernel = new ConcreteMicroKernel('test', false);
        $kernel->registerContainerConfiguration(new ClosureLoader($container));
        $this->assertTrue($container->getDefinition('kernel')->hasTag('routing.route_loader'));
    }
}
