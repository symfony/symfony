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

use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('web_link.add_link_header_listener', AddLinkHeaderListener::class)
            ->tag('kernel.event_subscriber')
    ;
};
