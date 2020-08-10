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

use Symfony\Component\Translation\Bridge\Crowdin\CrowdinRemoteFactory;
use Symfony\Component\Translation\Bridge\Loco\LocoRemoteFactory;
use Symfony\Component\Translation\Remote\AbstractRemoteFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('translation.remote_factory.abstract', AbstractRemoteFactory::class)
            ->args([
                service('http_client')->ignoreOnInvalid(),
                service('translation.loader.xliff_raw'),
                service('logger')->nullOnInvalid(),
                param('kernel.default_locale'),
            ])
            ->abstract()

        ->set('translation.remote_factory.loco', LocoRemoteFactory::class)
            ->args([
                service('translator.data_collector')->nullOnInvalid(),
            ])
            ->parent('translation.remote_factory.abstract')
            ->tag('translation.remote_factory')

        ->set('translation.remote_factory.crowdin', CrowdinRemoteFactory::class)
            ->args([
                service('translator.data_collector')->nullOnInvalid(),
            ])
            ->parent('translation.remote_factory.abstract')
            ->tag('translation.remote_factory')
    ;
};
