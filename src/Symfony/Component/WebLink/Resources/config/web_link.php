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
use Symfony\Component\WebLink\JsonLinksetParser;
use Symfony\Component\WebLink\JsonLinksetSerializer;
use Symfony\Component\WebLink\LinkTemplateHeaderParser;
use Symfony\Component\WebLink\LinkTemplateHeaderSerializer;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('web_link.http_header_serializer', HttpHeaderSerializer::class)
        ->alias(HttpHeaderSerializer::class, 'web_link.http_header_serializer')

        ->set('web_link.http_header_parser', HttpHeaderParser::class)
        ->alias(HttpHeaderParser::class, 'web_link.http_header_parser')

        ->set('web_link.link_template_header_serializer', LinkTemplateHeaderSerializer::class)
        ->alias(LinkTemplateHeaderSerializer::class, 'web_link.link_template_header_serializer')

        ->set('web_link.link_template_header_parser', LinkTemplateHeaderParser::class)
        ->alias(LinkTemplateHeaderParser::class, 'web_link.link_template_header_parser')

        ->set('web_link.json_linkset_serializer', JsonLinksetSerializer::class)
        ->alias(JsonLinksetSerializer::class, 'web_link.json_linkset_serializer')

        ->set('web_link.json_linkset_parser', JsonLinksetParser::class)
        ->alias(JsonLinksetParser::class, 'web_link.json_linkset_parser')

        ->set('web_link.add_link_header_listener', AddLinkHeaderListener::class)
            ->args([
                service('web_link.http_header_serializer'),
                service('web_link.link_template_header_serializer'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
