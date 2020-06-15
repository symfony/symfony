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

use Symfony\Component\HttpKernel\EventListener\AddRequestFormatsListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('request.add_request_formats_listener', AddRequestFormatsListener::class)
            ->args([abstract_arg('formats')])
            ->tag('kernel.event_subscriber')
    ;
};
