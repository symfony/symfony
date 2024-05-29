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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\DependencyInjection\LoggingTranslatorPass;
use Symfony\Component\Translation\Translator;

class LoggingTranslatorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('translator.logging', true);
        $container->setParameter('translator.class', Translator::class);
        $container->register('monolog.logger');
        $container->setAlias('logger', 'monolog.logger');
        $container->register('translator.default', '%translator.class%');
        $container->register('translator.logging', '%translator.class%');
        $container->setAlias('translator', 'translator.default');
        $translationWarmerDefinition = $container->register('translation.warmer')
            ->addArgument(new Reference('translator'))
            ->addTag('container.service_subscriber', ['id' => 'translator'])
            ->addTag('container.service_subscriber', ['id' => 'foo']);

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        $this->assertEquals(
            ['container.service_subscriber' => [
                ['id' => 'foo'],
                ['key' => 'translator', 'id' => 'translator.logging.inner'],
            ]],
            $translationWarmerDefinition->getTags()
        );
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('identity_translator');
        $container->setAlias('translator', 'identity_translator');

        $definitionsBefore = \count($container->getDefinitions());
        $aliasesBefore = \count($container->getAliases());

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

        $definitionsBefore = \count($container->getDefinitions());
        $aliasesBefore = \count($container->getAliases());

        $pass = new LoggingTranslatorPass();
        $pass->process($container);

        // the container is untouched (i.e. no new definitions or aliases)
        $this->assertCount($definitionsBefore, $container->getDefinitions());
        $this->assertCount($aliasesBefore, $container->getAliases());
    }
}
