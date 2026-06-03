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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Translation\Extractor\Visitor\ConstraintVisitor;
use Symfony\Component\Validator\Constraints\IsbnValidator;
use Symfony\Component\Validator\Constraints\LengthValidator;
use Symfony\Component\Validator\Constraints\NotBlankValidator;
use Symfony\Component\Validator\Constraints\TimeValidator;

class TranslatorPassTest extends TestCase
{
    public function testValidCollector()
    {
        $loader = (new Definition())
            ->addTag('translation.loader', ['alias' => 'xliff', 'legacy-alias' => 'xlf']);

        $reader = new Definition();

        $translator = (new Definition())
            ->setArguments([null, null, null, null]);

        $container = new ContainerBuilder();
        $container->setDefinition('translator.default', $translator);
        $container->setDefinition('translation.reader', $reader);
        $container->setDefinition('translation.xliff_loader', $loader);

        $pass = new TranslatorPass();
        $pass->process($container);

        $expectedReader = (new Definition())
            ->addMethodCall('addLoader', ['xliff', new Reference('translation.xliff_loader')])
            ->addMethodCall('addLoader', ['xlf', new Reference('translation.xliff_loader')])
        ;
        $this->assertEquals($expectedReader, $reader);

        $expectedLoader = (new Definition())
            ->addTag('translation.loader', ['alias' => 'xliff', 'legacy-alias' => 'xlf'])
        ;
        $this->assertEquals($expectedLoader, $loader);

        $this->assertSame(['translation.xliff_loader' => ['xliff', 'xlf']], $translator->getArgument(3));

        $expected = ['translation.xliff_loader' => new ServiceClosureArgument(new Reference('translation.xliff_loader'))];
        $this->assertEquals($expected, $container->getDefinition((string) $translator->getArgument(0))->getArgument(0));
    }

    public function testValidCommandsViewPathsArgument()
    {
        $container = new ContainerBuilder();
        $container->register('translator.default')
            ->setArguments([null, null, null, null])
        ;
        $debugCommand = $container->register('console.command.translation_debug')
            ->setArguments([null, null, null, null, null, [], []])
        ;
        $updateCommand = $container->register('console.command.translation_extract')
            ->setArguments([null, null, null, null, null, null, [], []])
        ;
        $container->register('twig.template_iterator')
            ->setArguments([null, ['other/templates' => null, 'tpl' => 'App']])
        ;
        $container->setParameter('twig.default_path', 'templates');

        $pass = new TranslatorPass();
        $pass->process($container);

        $expectedViewPaths = ['other/templates', 'tpl'];

        $this->assertSame('templates', $debugCommand->getArgument(4));
        $this->assertSame('templates', $updateCommand->getArgument(5));
        $this->assertSame($expectedViewPaths, $debugCommand->getArgument(6));
        $this->assertSame($expectedViewPaths, $updateCommand->getArgument(7));
    }

    public function testCommandsViewPathsArgumentsAreIgnoredWithOldServiceDefinitions()
    {
        $container = new ContainerBuilder();
        $container->register('translator.default')
            ->setArguments([null, null, null, null])
        ;
        $debugCommand = $container->register('console.command.translation_debug')
            ->setArguments([
                new Reference('translator'),
                new Reference('translation.reader'),
                new Reference('translation.extractor'),
                '%translator.default_path%',
                null,
            ])
        ;
        $updateCommand = $container->register('console.command.translation_extract')
            ->setArguments([
                new Reference('translation.writer'),
                new Reference('translation.reader'),
                new Reference('translation.extractor'),
                '%kernel.default_locale%',
                '%translator.default_path%',
                null,
            ])
        ;
        $container->register('twig.template_iterator')
            ->setArguments([null, ['other/templates' => null, 'tpl' => 'App']])
        ;
        $container->setParameter('twig.default_path', 'templates');

        $pass = new TranslatorPass();
        $pass->process($container);

        $this->assertSame('templates', $debugCommand->getArgument(4));
        $this->assertSame('templates', $updateCommand->getArgument(5));
    }

    public function testValidPhpAstExtractorConstraintVisitorArguments()
    {
        $container = new ContainerBuilder();
        $container->register('translator.default')
            ->setArguments([null, null, null, null]);
        $container->register('validator');
        $constraintVisitor = $container->register('translation.extractor.visitor.constraint', ConstraintVisitor::class);
        $container->register('validator.not_blank', NotBlankValidator::class)
            ->addTag('validator.constraint_validator');
        $container->register('validator.isbn', IsbnValidator::class)
            ->addTag('validator.constraint_validator');
        $container->register('validator.length', LengthValidator::class)
            ->addTag('validator.constraint_validator');
        $container->register('validator.time', '%foo.time.validator.class%')
            ->addTag('validator.constraint_validator');
        $container->setParameter('foo.time.validator.class', TimeValidator::class);

        $pass = new TranslatorPass();
        $pass->process($container);

        $this->assertSame(['NotBlank', 'Isbn', 'Length', 'Time'], $constraintVisitor->getArgument(0));
    }
}
