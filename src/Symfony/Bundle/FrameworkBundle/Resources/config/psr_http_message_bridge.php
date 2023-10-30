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

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver;
use Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('psr_http_message_bridge.http_foundation_factory', HttpFoundationFactory::class)
        ->alias(HttpFoundationFactoryInterface::class, 'psr_http_message_bridge.http_foundation_factory')

        ->set('psr_http_message_bridge.psr_http_factory', PsrHttpFactory::class)
            ->args([
                service(ServerRequestFactoryInterface::class)->nullOnInvalid(),
                service(StreamFactoryInterface::class)->nullOnInvalid(),
                service(UploadedFileFactoryInterface::class)->nullOnInvalid(),
                service(ResponseFactoryInterface::class)->nullOnInvalid(),
            ])
        ->alias(HttpMessageFactoryInterface::class, 'psr_http_message_bridge.psr_http_factory')

        ->set('psr_http_message_bridge.psr_server_request_resolver', PsrServerRequestResolver::class)
            ->args([service('psr_http_message_bridge.psr_http_factory')])
            ->tag('controller.argument_value_resolver', ['priority' => -100])

        ->set('psr_http_message_bridge.psr_response_listener', PsrResponseListener::class)
            ->args([
                service('psr_http_message_bridge.http_foundation_factory'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
