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

use Symfony\Component\Cache\CacheWarmer\CachePoolClearerCacheWarmer;

return static function (ContainerConfigurator $container) {
    $container->services()
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
