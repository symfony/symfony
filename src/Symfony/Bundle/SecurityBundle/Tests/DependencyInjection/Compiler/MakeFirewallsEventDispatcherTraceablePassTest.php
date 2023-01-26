<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;

class MakeFirewallsEventDispatcherTraceablePassTest extends TestCase
{
    private $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->register('request_stack', \stdClass::class);
        $this->container->register('event_dispatcher', EventDispatcher::class);
        $this->container->register('debug.stopwatch', Stopwatch::class);

        $this->container->registerExtension(new SecurityExtension());
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true]],
        ]);

        $this->container->addCompilerPass(new DecoratorServicePass(), PassConfig::TYPE_OPTIMIZE);
        $this->container->getCompilerPassConfig()->setRemovingPasses([]);
        $this->container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $securityBundle = new SecurityBundle();
        $securityBundle->build($this->container);
    }

    public function testEventDispatcherIsDecoratedOnDebugMode()
    {
        $this->container->setParameter('kernel.debug', true);

        $this->container->compile();

        $dispatcherDefinition = $this->container->findDefinition('security.event_dispatcher.main');

        $this->assertSame(TraceableEventDispatcher::class, $dispatcherDefinition->getClass());
        $this->assertSame(
            [['name' => 'security.event_dispatcher.main']],
            $dispatcherDefinition->getTag('event_dispatcher.dispatcher')
        );
    }

    public function testEventDispatcherIsNotDecoratedOnNonDebugMode()
    {
        $this->container->setParameter('kernel.debug', false);

        $this->container->compile();

        $dispatcherDefinition = $this->container->findDefinition('security.event_dispatcher.main');

        $this->assertSame(EventDispatcher::class, $dispatcherDefinition->getClass());
    }
}
