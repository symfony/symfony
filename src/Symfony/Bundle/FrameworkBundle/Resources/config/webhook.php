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

use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Controller\WebhookController;
use Symfony\Component\Webhook\Messenger\SendWebhookHandler;
use Symfony\Component\Webhook\Server\HeadersConfigurator;
use Symfony\Component\Webhook\Server\HeaderSignatureConfigurator;
use Symfony\Component\Webhook\Server\JsonBodyConfigurator;
use Symfony\Component\Webhook\Server\Transport;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('webhook.transport', Transport::class)
            ->args([
                service('http_client'),
                service('webhook.headers_configurator'),
                service('webhook.body_configurator.json'),
                service('webhook.signer'),
            ])

        ->set('webhook.headers_configurator', HeadersConfigurator::class)

        ->set('webhook.body_configurator.json', JsonBodyConfigurator::class)
            ->args([
                service('serializer'),
            ])

        ->set('webhook.signer', HeaderSignatureConfigurator::class)

        ->set('webhook.messenger.send_handler', SendWebhookHandler::class)
            ->args([
                service('webhook.transport'),
            ])
            ->tag('messenger.message_handler')

        ->set('webhook.request_parser', RequestParser::class)
        ->alias(RequestParser::class, 'webhook.request_parser')

        ->set('webhook.controller', WebhookController::class)
            ->public()
            ->args([
                abstract_arg('user defined parsers'),
                abstract_arg('message bus'),
            ])
    ;
};
