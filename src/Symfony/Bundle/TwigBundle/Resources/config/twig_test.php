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

use Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.error_renderer.html', TwigErrorRenderer::class)
        ->decorate('error_renderer.html')
        ->args([
            service('twig'),
            inline_service(CliErrorRenderer::class),
            inline_service('bool')
                ->factory([TwigErrorRenderer::class, 'isDebug'])
                ->args([service('request_stack'), param('kernel.debug')]),
        ])
    ;
};
