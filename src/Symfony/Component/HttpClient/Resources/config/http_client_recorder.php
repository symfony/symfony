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
use Symfony\Component\HttpClient\Recorder\RecorderConfiguration;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\RecorderHttpClient;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('http_client.recorder.configuration', class_exists(HttpRecorder::class) ? HttpRecorder::class : RecorderConfiguration::class)
        ->set('http_client.recorder.redactor', DefaultRedactor::class)

        // innermost decorator of the transport (just outside the real client, or the mock), so it sees
        // absolute URLs after ScopingHttpClient; a retried attempt is canceled, so only the last one is recorded
        ->set('http_client.recorder', RecorderHttpClient::class)
            ->decorate('http_client.transport', null, 100)
            ->args([
                service('.inner'),
                service('http_client.recorder.configuration'),
                service('http_client.recorder.redactor'),
                abstract_arg('default options'),
            ])
    ;
};
