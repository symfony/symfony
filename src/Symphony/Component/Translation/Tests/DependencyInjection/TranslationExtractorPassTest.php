<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Translation\DependencyInjection\TranslationExtractorPass;

class TranslationExtractorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $extractorDefinition = $container->register('translation.extractor');
        $container->register('foo.id')
            ->addTag('translation.extractor', array('alias' => 'bar.alias'));

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);

        $this->assertEquals(array(array('addExtractor', array('bar.alias', new Reference('foo.id')))), $extractorDefinition->getMethodCalls());
    }

    public function testProcessNoDefinitionFound()
    {
        $container = new ContainerBuilder();

        $definitionsBefore = count($container->getDefinitions());
        $aliasesBefore = count($container->getAliases());

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage The alias for the tag "translation.extractor" of service "foo.id" must be set.
     */
    public function testProcessMissingAlias()
    {
        $definition = $this->getMockBuilder('Symphony\Component\DependencyInjection\Definition')->disableOriginalConstructor()->getMock();
        $container = new ContainerBuilder();
        $container->register('translation.extractor');
        $container->register('foo.id')
            ->addTag('translation.extractor', array());

        $definition->expects($this->never())->method('addMethodCall');

        $translationDumperPass = new TranslationExtractorPass();
        $translationDumperPass->process($container);
    }
}
