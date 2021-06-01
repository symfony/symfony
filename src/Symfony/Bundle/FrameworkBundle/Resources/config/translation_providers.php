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

use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProviderFactory;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProviderFactory;
use Symfony\Component\Translation\Provider\NullProviderFactory;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Provider\TranslationProviderCollectionFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translation.provider_collection', TranslationProviderCollection::class)
            ->factory([service('translation.provider_collection_factory'), 'fromConfig'])
            ->args([
                [], // Providers
            ])

        ->set('translation.provider_collection_factory', TranslationProviderCollectionFactory::class)
            ->args([
                tagged_iterator('translation.provider_factory'),
                [], // Enabled locales
            ])

        ->set('translation.provider_factory.null', NullProviderFactory::class)
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.crowdin', CrowdinProviderFactory::class)
            ->args([
                service('http_client'),
                service('logger'),
                param('kernel.default_locale'),
                service('translation.loader.xliff'),
                service('translation.dumper.xliff'),
            ])
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.loco', LocoProviderFactory::class)
            ->args([
                service('http_client'),
                service('logger'),
                param('kernel.default_locale'),
                service('translation.loader.xliff'),
            ])
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.lokalise', LokaliseProviderFactory::class)
            ->args([
                service('http_client'),
                service('logger'),
                param('kernel.default_locale'),
                service('translation.loader.xliff'),
            ])
            ->tag('translation.provider_factory')
    ;
};
