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

use Symfony\Bridge\Twig\Extension\ImportMapsExtension;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('twig.extension.importmaps', ImportMapsExtension::class)
            ->args([
                service('importmaps.manager'),
                param('importmaps.polyfill'),
            ])
            ->tag('twig.extension')

    ;
};
