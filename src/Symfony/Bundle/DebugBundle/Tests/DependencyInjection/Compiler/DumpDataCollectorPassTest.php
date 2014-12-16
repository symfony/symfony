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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DumpDataCollectorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessWithFileLinkFormatParameter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());
        $container->setParameter('templating.helper.code.file_link_format', 'file-link-format');

        $definition = new Definition('Symfony\Component\HttpKernel\DataCollector\DumpDataCollector', array(null, null));
        $container->setDefinition('data_collector.dump', $definition);

        $container->compile();

        $this->assertSame('file-link-format', $definition->getArgument(1));
    }

    public function testProcessWithoutFileLinkFormatParameter()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new DumpDataCollectorPass());

        $definition = new Definition('Symfony\Component\HttpKernel\DataCollector\DumpDataCollector', array(null, null));
        $container->setDefinition('data_collector.dump', $definition);

        $container->compile();

        $this->assertNull($definition->getArgument(1));
    }
}
