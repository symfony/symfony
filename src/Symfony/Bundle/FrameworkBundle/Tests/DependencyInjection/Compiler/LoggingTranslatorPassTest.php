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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LoggingTranslatorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', true);
        $container->setParameter('translator.class', 'Symfony\Component\Translation\Translator');
        $container->register('monolog.logger');
        $container->setAlias('logger', 'monolog.logger');
        $container->register('translator.default', '%translator.class%');
        $container->register('translator.logging', '%translator.class%');
        $container->setAlias('translator', 'translator.default');
        $translationWarmerDefinition = $container->register('translation.warmer')->addArgument(new Reference('translator'));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        $this->assertEquals(new Reference('translator.logging.inner'), $translationWarmerDefinition->getArgument(0));
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('identity_translator');
        $container->setAlias('translator', 'identity_translator');

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        // we just check that the compiler pass does not break if a logger is not registered
        $this->addToAssertionCount(1);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotTranslatorDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('monolog.logger');
        $container->setAlias('logger', 'monolog.logger');

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        // we just check that the compiler pass does not break if a translator is not registered
        $this->addToAssertionCount(1);
    }
}
