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

use Symfony\Bridge\Twig\Extension\ImportMapExtension;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('twig.runtime.importmap', ImportMapRuntime::class)
            ->args([service('asset_mapper.importmap.renderer')])
            ->tag('twig.runtime')

        ->set('twig.extension.importmap', ImportMapExtension::class)
            ->tag('twig.extension')
    ;
};
