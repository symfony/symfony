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

use Symfony\Component\Cache\Command\CachePoolClearCommand;
use Symfony\Component\Cache\Command\CachePoolDeleteCommand;
use Symfony\Component\Cache\Command\CachePoolInvalidateTagsCommand;
use Symfony\Component\Cache\Command\CachePoolListCommand;
use Symfony\Component\Cache\Command\CachePoolPruneCommand;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('console.command.cache_pool_clear', CachePoolClearCommand::class)
            ->args([
                service('service_container'),
                service('cache.global_clearer'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_prune', CachePoolPruneCommand::class)
            ->args([
                [],
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_invalidate_tags', CachePoolInvalidateTagsCommand::class)
            ->args([
                tagged_locator('cache.taggable', 'pool'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_delete', CachePoolDeleteCommand::class)
            ->args([
                service('cache.global_clearer'),
            ])
            ->tag('console.command')

        ->set('console.command.cache_pool_list', CachePoolListCommand::class)
            ->args([
                null,
            ])
            ->tag('console.command')
    ;
};
