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

use Symfony\Bundle\TwigBundle\Routing\TemplateRouteLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.routing.template_loader', TemplateRouteLoader::class)
            ->decorate('routing.loader', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([service('.inner')])
    ;
};
