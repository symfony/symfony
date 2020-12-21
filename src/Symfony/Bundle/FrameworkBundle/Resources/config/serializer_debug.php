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
use Symfony\Component\Serializer\Debug\SerializerActionFactory;
use Symfony\Component\Serializer\Debug\SerializerActionFactoryInterface;
use Symfony\Component\Serializer\Debug\TraceableSerializer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('debug.serializer', TraceableSerializer::class)
            ->decorate('serializer', null, 255)
            ->args([service('debug.serializer.inner'), service('debug.serializer.action_factory')])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('data_collector.serializer', SerializerDataCollector::class)
            ->args([service('debug.serializer'), tagged_iterator('debug.normalizer')])
            ->tag('data_collector', ['template' => '@WebProfiler/Collector/serializer.html.twig', 'id' => 'serializer'])

        ->set('debug.serializer.action_factory', SerializerActionFactory::class)

        ->alias(SerializerActionFactoryInterface::class, 'debug.serializer.action_factory')
    ;
};
