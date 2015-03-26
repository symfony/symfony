<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LoggingTranslatorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', true);
        $container->setParameter('translator.logging_excluded_domains', array('foo'));

        $container->register('default_logger', '\stdClass');
        $container->setAlias('logger', 'default_logger');
        $this->process($container);

        $loggingTranslatorDefinition = $container->getDefinition('translator.logging');
        $this->assertSame(array('translator', null), $loggingTranslatorDefinition->getDecoratedService());
        $this->assertSame(array('foo'), $loggingTranslatorDefinition->getArgument(2));
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', true);

        $this->process($container);

        $this->assertNull($container->getDefinition('translator.logging')->getDecoratedService());
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotTranslatorDefinition()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', false);

        $container->register('default_logger', '\stdClass');
        $container->setAlias('logger', 'default_logger');
        $this->process($container);

        $this->assertNull($container->getDefinition('translator.logging')->getDecoratedService());
    }

    protected function process(ContainerBuilder $container)
    {
        $container->register('default_translator', '\Symfony\Component\Translation\Translator');
        $container->setAlias('translator', 'default_translator');
        $container->register('translator.logging', '\Symfony\Component\Translation\LoggingTranslator');

        $loggingTranslatorDefinition = $container->getDefinition('translator.logging');
        $loggingTranslatorDefinition->setArguments(array('foo', 'barr', null));

        $loggingTranslatorPass = new LoggingTranslatorPass();
        $loggingTranslatorPass->process($container);
    }
}
