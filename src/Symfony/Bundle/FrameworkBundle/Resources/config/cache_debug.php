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

use Symfony\Bundle\FrameworkBundle\CacheWarmer\CachePoolClearerCacheWarmer;
use Symfony\Component\Cache\DataCollector\CacheDataCollector;

return static function (ContainerConfigurator $container) {
    $container->services()
        // DataCollector (public to prevent inlining, made private in CacheCollectorPass)
        ->set('data_collector.cache', CacheDataCollector::class)
            ->public()
            ->tag('data_collector', [
                'template' => '@WebProfiler/Collector/cache.html.twig',
                'id' => 'cache',
                'priority' => 275,
            ])

        // CacheWarmer used in dev to clear cache pool
        ->set('cache_pool_clearer.cache_warmer', CachePoolClearerCacheWarmer::class)
            ->args([
                service('cache.system_clearer'),
                [
                    'cache.validator',
                    'cache.serializer',
                ],
            ])
            ->tag('kernel.cache_warmer', ['priority' => 64])
    ;
};
