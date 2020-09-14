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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Retry\ExponentialBackOff;
use Symfony\Component\HttpClient\Retry\HttpStatusCodeDecider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('http_client', HttpClientInterface::class)
            ->factory([HttpClient::class, 'create'])
            ->args([
                [], // default options
                abstract_arg('max host connections'),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'http_client'])
            ->tag('http_client.client')

        ->alias(HttpClientInterface::class, 'http_client')

        ->set('psr18.http_client', Psr18Client::class)
            ->args([
                service('http_client'),
                service(ResponseFactoryInterface::class)->ignoreOnInvalid(),
                service(StreamFactoryInterface::class)->ignoreOnInvalid(),
            ])

        ->alias(ClientInterface::class, 'psr18.http_client')

        ->set(\Http\Client\HttpClient::class, HttplugClient::class)
            ->args([
                service('http_client'),
                service(ResponseFactoryInterface::class)->ignoreOnInvalid(),
                service(StreamFactoryInterface::class)->ignoreOnInvalid(),
            ])

        // retry
        ->set('http_client.retry.abstract_exponential_backoff', ExponentialBackOff::class)
            ->abstract()
            ->args([
                abstract_arg('delay ms'),
                abstract_arg('multiplier'),
                abstract_arg('max delay ms'),
            ])
        ->set('http_client.retry.abstract_httpstatuscode_decider', HttpStatusCodeDecider::class)
            ->abstract()
            ->args([
                abstract_arg('http codes'),
            ])
    ;
};
