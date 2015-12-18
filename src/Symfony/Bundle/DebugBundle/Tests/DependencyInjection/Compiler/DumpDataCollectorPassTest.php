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

use Symfony\Bundle\DebugBundle\DependencyInjection\Compiler\DumpDataCollectorPass;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class DumpDataCollectorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessWithFileLinkFormatParameter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());
        $container->setParameter('templating.helper.code.file_link_format', 'file-link-format');

        $definition = new Definition('Symfony\Component\VarDumper\Dumper\TraceableDumper', array(null, null, null, null));
        $container->setDefinition('var_dumper.traceable_dumper', $definition);

        $container->compile();

        $this->assertSame('file-link-format', $definition->getArgument(2));
    }

    public function testProcessWithoutFileLinkFormatParameter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition('Symfony\Component\VarDumper\Dumper\TraceableDumper', array(null, null, null, null));
        $container->setDefinition('var_dumper.traceable_dumper', $definition);

        $container->compile();

        $this->assertNull($definition->getArgument(2));
    }

    public function testProcessWithToolbarEnabled()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());
        $dumper = new HtmlDumper();

        $definition = new Definition('Symfony\Component\VarDumper\Dumper\TraceableDumper', array($dumper, null, null, null));
        $container->setDefinition('var_dumper.traceable_dumper', $definition);
        $container->setParameter('web_profiler.debug_toolbar.mode', WebDebugToolbarListener::ENABLED);

        $container->compile();

        $this->assertSame($dumper, $definition->getArgument(0));
    }

    public function testProcessWithToolbarDisabled()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition('Symfony\Component\VarDumper\Dumper\TraceableDumper', array(new HtmlDumper(), null, null, null));
        $container->setDefinition('var_dumper.traceable_dumper', $definition);
        $container->setParameter('web_profiler.debug_toolbar.mode', WebDebugToolbarListener::DISABLED);

        $container->compile();

        $this->assertNull($definition->getArgument(0));
    }

    public function testProcessWithoutToolbar()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition('Symfony\Component\VarDumper\Dumper\TraceableDumper', array(new HtmlDumper(), null, null, null));
        $container->setDefinition('var_dumper.traceable_dumper', $definition);

        $container->compile();

        $this->assertNull($definition->getArgument(0));
    }
}
