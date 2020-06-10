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
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.extension.form', FormExtension::class)

        ->set('twig.form.engine', TwigRendererEngine::class)
            ->args([param('twig.form.resources'), service('twig')])

        ->set('twig.form.renderer', FormRenderer::class)
            ->args([service('twig.form.engine'), service('security.csrf.token_manager')->nullOnInvalid()])
            ->tag('twig.runtime')
    ;
};
