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

use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\Matcher\MatcherInterface;
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\Recorder\Redactor\RedactorInterface;
use Symfony\Component\HttpClient\Recorder\Store\FilesystemStore;
use Symfony\Component\HttpClient\RecorderHttpClient;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('http_client.recorder.store', FilesystemStore::class)

        // the PHPUnit bridge implementation is driven by #[UseRecord]; outside of PHPUnit the recorder stays in passthrough
        ->set('http_client.recorder.configuration', class_exists(HttpRecorder::class) ? HttpRecorder::class : RecorderConfiguration::class)
        ->alias(RecorderConfigurationInterface::class, 'http_client.recorder.configuration')

        ->set('http_client.recorder.matcher', DefaultMatcher::class)
            ->args([service('http_client.recorder.redactor')])
        ->alias(MatcherInterface::class, 'http_client.recorder.matcher')
        ->set('http_client.recorder.redactor', DefaultRedactor::class)
        ->alias(RedactorInterface::class, 'http_client.recorder.redactor')

        // innermost decorator of the transport (just outside the real client, or the mock), so it sees
        // absolute URLs after ScopingHttpClient and records each retry attempt, appending and replaying entries in order
        ->set('http_client.recorder', RecorderHttpClient::class)
            ->decorate('http_client.transport', null, \PHP_INT_MAX - 1)
            ->args([
                service('.inner'),
                service('http_client.recorder.store'),
                service('http_client.recorder.configuration'),
                service('http_client.recorder.matcher'),
                service('http_client.recorder.redactor'),
                abstract_arg('default options'),
            ])
    ;
};
