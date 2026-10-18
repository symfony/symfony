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

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TraceableTwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.extension.form', FormExtension::class)
            ->args([service('translator')->nullOnInvalid()])

        ->set('twig.form.engine', TwigRendererEngine::class)
            ->args([param('twig.form.resources'), service('twig')])
            ->tag('kernel.reset', ['method' => '?reset'])

        ->set('debug.twig.form.engine', TraceableTwigRendererEngine::class)
            ->decorate('twig.form.engine')
            ->args([
                service('.inner'),
                service('twig'),
                param('twig.form.resources'),
                service('data_collector.form'),
            ])
            ->tag('kernel.reset', ['method' => 'reset'])
            ->tag('container.remove_if_missing', ['service' => 'data_collector.form', 'class' => TraceableTwigRendererEngine::class])

        ->set('twig.form.renderer', FormRenderer::class)
            ->args([service('twig.form.engine'), service('security.csrf.token_manager')->nullOnInvalid()])
            ->tag('twig.runtime')
    ;
};
