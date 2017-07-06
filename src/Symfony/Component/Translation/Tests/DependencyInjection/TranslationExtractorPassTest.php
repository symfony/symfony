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
use Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass;

class TranslationExtractorPassTest extends TestCase
{
    public function testProcess()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('translation.extractor')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('getDefinition')
            ->with('translation.extractor')
            ->will($this->returnValue($definition));

        $valueTaggedServiceIdsFound = array(
            'foo.id' => array(
                array('alias' => 'bar.alias'),
            ),
        );
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('translation.extractor', true)
            ->will($this->returnValue($valueTaggedServiceIdsFound));

        $definition->expects($this->once())->method('addMethodCall')->with('addExtractor', array('bar.alias', new Reference('foo.id')));

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);
    }

    public function testProcessNoDefinitionFound()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('translation.extractor')
            ->will($this->returnValue(false));

        $container->expects($this->never())->method('getDefinition');
        $container->expects($this->never())->method('findTaggedServiceIds');

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage The alias for the tag "translation.extractor" of service "foo.id" must be set.
     */
    public function testProcessMissingAlias()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->disableOriginalConstructor()->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->disableOriginalConstructor()->getMock();

        $container->expects($this->once())
            ->method('hasDefinition')
            ->with('translation.extractor')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('getDefinition')
            ->with('translation.extractor')
            ->will($this->returnValue($definition));

        $valueTaggedServiceIdsFound = array(
            'foo.id' => array(),
        );
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('translation.extractor', true)
            ->will($this->returnValue($valueTaggedServiceIdsFound));

        $definition->expects($this->never())->method('addMethodCall');

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);
    }
}
