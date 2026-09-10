<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Translation\Command\TranslationDebugCommand;
use Symfony\Component\Translation\Command\TranslationExtractCommand;
use Symfony\Component\Translation\Command\TranslationLintCommand;
use Symfony\Component\Translation\Command\TranslationPullCommand;
use Symfony\Component\Translation\Command\TranslationPushCommand;
use Symfony\Component\Translation\Command\XliffUpdateSourcesCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.command.translation_debug', TranslationDebugCommand::class)
            ->args([
                service('kernel'),
                service('translator'),
                service('translation.reader'),
                service('translation.extractor'),
                param('translator.default_path'),
                null, // twig.default_path
                abstract_arg('the paths translations are read from'),
                [], // Twig paths
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('console.command.translation_extract', TranslationExtractCommand::class)
            ->args([
                service('kernel'),
                service('translation.writer'),
                service('translation.reader'),
                service('translation.extractor'),
                param('kernel.default_locale'),
                param('translator.default_path'),
                null, // twig.default_path
                abstract_arg('the paths translations are read from'),
                [], // Twig paths
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('console.command.translation_pull', TranslationPullCommand::class)
            ->args([
                service('translation.provider_collection'),
                service('translation.writer'),
                service('translation.reader'),
                param('kernel.default_locale'),
                abstract_arg('the paths translations are read from'),
                [], // the locales, merged with the enabled ones by RemoveMissingDependenciesPass
            ])
            ->tag('console.command', ['command' => 'translation:pull'])

        ->set('console.command.translation_push', TranslationPushCommand::class)
            ->args([
                service('translation.provider_collection'),
                service('translation.reader'),
                abstract_arg('the paths translations are read from'),
                [], // the locales, merged with the enabled ones by RemoveMissingDependenciesPass
            ])
            ->tag('console.command', ['command' => 'translation:push'])

        ->set('console.command.translation_lint', TranslationLintCommand::class)
            ->args([
                service('translator'),
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')

        ->set('console.command.translation_xliff_update_sources', XliffUpdateSourcesCommand::class)
            ->args([
                service('translation.writer'),
                service('translation.reader'),
                param('kernel.default_locale'),
                abstract_arg('the paths the sources are updated in'),
                param('kernel.enabled_locales'),
            ])
            ->tag('console.command')
    ;
};
