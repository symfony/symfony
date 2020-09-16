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

use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('data_collector.http_client', HttpClientDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/http_client.html.twig',
                'id' => 'http_client',
                'priority' => 250,
            ])
    ;
};
