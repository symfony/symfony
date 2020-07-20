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

use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Mailer\EventListener\MessageListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.mailer.message_listener', MessageListener::class)
            ->args([null, service('twig.mime_body_renderer')])
            ->tag('kernel.event_subscriber')

        ->set('twig.mime_body_renderer', BodyRenderer::class)
            ->args([service('twig')])
    ;
};
