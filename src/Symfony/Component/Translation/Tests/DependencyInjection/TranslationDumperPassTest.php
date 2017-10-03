<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass;

class TranslationDumperPassTest extends TestCase
{
    public function testProcess()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('translation.writer')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('getDefinition')
            ->with('translation.writer')
            ->will($this->returnValue($definition));

        $valueTaggedServiceIdsFound = array(
            'foo.id' => array(
                array('alias' => 'bar.alias'),
            ),
        );
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('translation.dumper', true)
            ->will($this->returnValue($valueTaggedServiceIdsFound));

        $definition->expects($this->once())->method('addMethodCall')->with('addDumper', array('bar.alias', new Reference('foo.id')));

        $translationDumperPass = new TranslationDumperPass();
        $translationDumperPass->process($container);
    }

    public function testProcessNoDefinitionFound()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('translation.writer')
            ->will($this->returnValue(false));

        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->never())->method('findTaggedServiceIds');

        $translationDumperPass = new TranslationDumperPass();
        $translationDumperPass->process($container);
    }
}
