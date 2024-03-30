<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\DebugBundle\DependencyInjection\Compiler\DumpDataCollectorPass;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;

class DumpDataCollectorPassTest extends TestCase
{
    public function testProcessWithoutFileLinkFormatParameter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition(DumpDataCollector::class, [null, null, new Reference('.virtual_request_stack'), null]);
        $container->setDefinition('data_collector.dump', $definition);

        $container->compile();

        $this->assertNull($definition->getArgument(1));
    }

    public function testProcessWithToolbarEnabledAndVirtualRequestStackPresent()
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class);
        $container->register('.virtual_request_stack', RequestStack::class);
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition(DumpDataCollector::class, [null, null, null, new Reference('.virtual_request_stack')]);
        $container->setDefinition('data_collector.dump', $definition);
        $container->setParameter('web_profiler.debug_toolbar.mode', WebDebugToolbarListener::ENABLED);

        $container->compile();

        $this->assertEquals(new Reference('.virtual_request_stack'), $definition->getArgument(3));
    }

    public function testProcessWithToolbarEnabledAndVirtualRequestStackNotPresent()
    {
        $container = new ContainerBuilder();
        $container->register('request_stack', RequestStack::class);
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition(DumpDataCollector::class, [null, null, null, new Reference('.virtual_request_stack')]);
        $container->setDefinition('data_collector.dump', $definition);
        $container->setParameter('web_profiler.debug_toolbar.mode', WebDebugToolbarListener::ENABLED);

        $container->compile();

        $this->assertEquals(new Reference('request_stack'), $definition->getArgument(3));
    }

    public function testProcessWithToolbarDisabled()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition(DumpDataCollector::class, [null, null, new Reference('.virtual_request_stack'), new RequestStack()]);
        $container->setDefinition('data_collector.dump', $definition);
        $container->setParameter('web_profiler.debug_toolbar.mode', WebDebugToolbarListener::DISABLED);

        $container->compile();

        $this->assertNull($definition->getArgument(3));
    }

    public function testProcessWithoutToolbar()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition(DumpDataCollector::class, [null, null, new Reference('.virtual_request_stack'), new RequestStack()]);
        $container->setDefinition('data_collector.dump', $definition);

        $container->compile();

        $this->assertNull($definition->getArgument(3));
    }
}
