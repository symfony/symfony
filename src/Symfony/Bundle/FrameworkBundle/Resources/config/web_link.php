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
use Symfony\Component\WebLink\HttpHeaderParser;
use Symfony\Component\WebLink\HttpHeaderSerializer;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('web_link.http_header_serializer', HttpHeaderSerializer::class)
        ->alias(HttpHeaderSerializer::class, 'web_link.http_header_serializer')

        ->set('web_link.http_header_parser', HttpHeaderParser::class)
        ->alias(HttpHeaderParser::class, 'web_link.http_header_parser')

        ->set('web_link.add_link_header_listener', AddLinkHeaderListener::class)
            ->args([
                service('web_link.http_header_serializer'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
