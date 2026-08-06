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
use Symfony\Bundle\SecurityBundle\DataCollector\EventListener\SecurityDataCollectorListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RemoveSecurityDataCollectorListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class RemoveSecurityDataCollectorListenerPassTest extends TestCase
{
    public function testListenerIsRemovedWhenTheProfilerIsNotEnabled()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('data_collector.security.listener', new Definition(SecurityDataCollectorListener::class));

        (new RemoveSecurityDataCollectorListenerPass())->process($container);

        $this->assertFalse($container->hasDefinition('data_collector.security.listener'));
    }

    public function testListenerIsKeptWhenTheProfilerIsEnabled()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('profiler', new Definition(Profiler::class));
        $container->setDefinition('data_collector.security.listener', new Definition(SecurityDataCollectorListener::class));

        (new RemoveSecurityDataCollectorListenerPass())->process($container);

        $this->assertTrue($container->hasDefinition('data_collector.security.listener'));
    }
}
