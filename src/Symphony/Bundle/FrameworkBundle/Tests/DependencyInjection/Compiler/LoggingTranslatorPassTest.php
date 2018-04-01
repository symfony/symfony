<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

class LoggingTranslatorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', true);
        $container->setParameter('translator.class', 'Symphony\Component\Translation\Translator');
        $container->register('monolog.logger');
        $container->setAlias('logger', 'monolog.logger');
        $container->register('translator.default', '%translator.class%');
        $container->register('translator.logging', '%translator.class%');
        $container->setAlias('translator', 'translator.default');
        $translationWarmerDefinition = $container->register('translation.warmer')
            ->addArgument(new Reference('translator'))
            ->addTag('container.service_subscriber', array('id' => 'translator'))
            ->addTag('container.service_subscriber', array('id' => 'foo'));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        $this->assertEquals(
            array('container.service_subscriber' => array(
                array('id' => 'foo'),
                array('key' => 'translator', 'id' => 'translator.logging.inner'),
            )),
            $translationWarmerDefinition->getTags()
        );
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('identity_translator');
        $container->setAlias('translator', 'identity_translator');

        $definitionsBefore = count($container->getDefinitions());
        $aliasesBefore = count($container->getAliases());

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotTranslatorDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('monolog.logger');
        $container->setAlias('logger', 'monolog.logger');

        $definitionsBefore = count($container->getDefinitions());
        $aliasesBefore = count($container->getAliases());

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }
}
