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

use Symfony\Component\AssetMapper\CacheWarmer\DebugAssetMapperCacheWarmer;

return static function (ContainerConfigurator $container) {
    if (class_exists(DebugAssetMapperCacheWarmer::class)) {
        $container->services()
            ->set('debug.asset_mapper.compile.cache_warmer', DebugAssetMapperCacheWarmer::class)
            ->args([
                service('asset_mapper.compiled_asset_mapper_config_reader'),
                service('asset_mapper.importmap.generator'),
            ])
            ->tag('kernel.cache_warmer');
    }
};
