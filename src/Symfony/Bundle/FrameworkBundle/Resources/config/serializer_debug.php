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

use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Debug\TraceableSerializer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('debug.serializer', TraceableSerializer::class)
            ->decorate('serializer')
            ->args([
                service('debug.serializer.inner'),
                service('serializer.data_collector'),
            ])

        ->set('serializer.data_collector', SerializerDataCollector::class)
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/serializer.html.twig',
                'id' => 'serializer',
            ])
    ;
};
