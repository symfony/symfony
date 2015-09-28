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

use Symfony\Bundle\DebugBundle\DependencyInjection\Compiler\AddFlattenExceptionProcessorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AddFlattenExceptionProcessorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AddFlattenExceptionProcessorPass());

        $definition = new Definition('Symfony\Component\Debug\ExceptionFlattener', array(array()));
        $container->setDefinition('exception_flattener', $definition);

        $processor1 = new Definition('Symfony\Component\Debug\FlattenExceptionProcessorInterface');
        $processor1->addTag('exception.processor', array('priority' => -100));

        $processor2 = new Definition('Symfony\Component\Debug\FlattenExceptionProcessorInterface');
        $processor2->addTag('exception.processor', array('priority' => 100));

        $container->setDefinition('processor_1', $processor1);
        $container->setDefinition('processor_2', $processor2);

        $container->compile();

        $this->assertEquals(array(array(new Reference('processor_2'), new Reference('processor_1'))), $definition->getArguments());
    }
}
