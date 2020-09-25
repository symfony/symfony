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
use Symfony\Component\Translation\Bridge\Phrase\PhraseProviderFactory;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Bridge\PoEditor\PoEditorProviderFactory;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translation.provider_factory.abstract', AbstractProviderFactory::class)
            ->args([
                service('http_client')->ignoreOnInvalid(),
                service('logger')->nullOnInvalid(),
                param('kernel.default_locale'),
            ])
            ->abstract()

        ->set('translation.provider_factory.loco', LocoProviderFactory::class)
            ->args([
                service('translation.loader.xliff_raw'),
            ])
            ->parent('translation.provider_factory.abstract')
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.crowdin', CrowdinProviderFactory::class)
            ->args([
                service('translation.loader.xliff_raw'),
            ])
            ->parent('translation.provider_factory.abstract')
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.phrase', PhraseProviderFactory::class)
            ->args([
                service('translation.loader.xliff_raw'),
            ])
            ->parent('translation.provider_factory.abstract')
            ->tag('translation.provider_factory')

        ->set('translation.provider_factory.poeditor', PoEditorProviderFactory::class)
            ->args([
                service('translation.loader.xliff_raw'),
            ])
            ->parent('translation.provider_factory.abstract')
            ->tag('translation.provider_factory')
    ;
};
