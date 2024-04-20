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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Messenger\EarlyExpirationHandler;
use Symfony\Component\HttpKernel\CacheClearer\Psr6CacheClearer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('cache.app')
            ->parent('cache.adapter.filesystem')
            ->public()
            ->tag('cache.pool', ['clearer' => 'cache.app_clearer'])

        ->set('cache.app.taggable', TagAwareAdapter::class)
            ->args([service('cache.app')])
            ->tag('cache.taggable', ['pool' => 'cache.app'])

        ->set('cache.system')
            ->parent('cache.adapter.system')
            ->public()
            ->tag('cache.pool')

        ->set('cache.validator')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('cache.serializer')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('cache.property_info')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('cache.asset_mapper')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('cache.messenger.restart_workers_signal')
            ->parent('cache.app')
            ->private()
            ->tag('cache.pool')

        ->set('cache.scheduler')
            ->parent('cache.app')
            ->private()
            ->tag('cache.pool')

        ->set('cache.adapter.system', AdapterInterface::class)
            ->abstract()
            ->factory([AbstractAdapter::class, 'createSystemCache'])
            ->args([
                '', // namespace
                0, // default lifetime
                abstract_arg('version'),
                sprintf('%s/pools/system', param('kernel.cache_dir')),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('cache.pool', ['clearer' => 'cache.system_clearer', 'reset' => 'reset'])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.apcu', ApcuAdapter::class)
            ->abstract()
            ->args([
                '', // namespace
                0, // default lifetime
                abstract_arg('version'),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.filesystem', FilesystemAdapter::class)
            ->abstract()
            ->args([
                '', // namespace
                0, // default lifetime
                sprintf('%s/pools/app', param('kernel.cache_dir')),
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.psr6', ProxyAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('PSR-6 provider service'),
                '', // namespace
                0, // default lifetime
            ])
            ->tag('cache.pool', [
                'provider' => 'cache.default_psr6_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])

        ->set('cache.adapter.redis', RedisAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('Redis connection service'),
                '', // namespace
                0, // default lifetime
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', [
                'provider' => 'cache.default_redis_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.redis_tag_aware', RedisTagAwareAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('Redis connection service'),
                '', // namespace
                0, // default lifetime
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', [
                'provider' => 'cache.default_redis_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.memcached', MemcachedAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('Memcached connection service'),
                '', // namespace
                0, // default lifetime
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', [
                'provider' => 'cache.default_memcached_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.doctrine_dbal', DoctrineDbalAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('DBAL connection service'),
                '', // namespace
                0, // default lifetime
                [], // table options
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', [
                'provider' => 'cache.default_doctrine_dbal_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.pdo', PdoAdapter::class)
            ->abstract()
            ->args([
                abstract_arg('PDO connection service'),
                '', // namespace
                0, // default lifetime
                [], // table options
                service('cache.default_marshaller')->ignoreOnInvalid(),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', [
                'provider' => 'cache.default_pdo_provider',
                'clearer' => 'cache.default_clearer',
                'reset' => 'reset',
            ])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.array', ArrayAdapter::class)
            ->abstract()
            ->args([
                0, // default lifetime
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
            ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.default_marshaller', DefaultMarshaller::class)
            ->args([
                null, // use igbinary_serialize() when available
                '%kernel.debug%',
            ])

        ->set('cache.early_expiration_handler', EarlyExpirationHandler::class)
            ->args([
                service('reverse_container'),
            ])
            ->tag('messenger.message_handler')

        ->set('cache.default_clearer', Psr6CacheClearer::class)
            ->args([
                [],
            ])

        ->set('cache.system_clearer')
            ->parent('cache.default_clearer')
            ->public()

        ->set('cache.global_clearer')
            ->parent('cache.default_clearer')
            ->public()

        ->alias('cache.app_clearer', 'cache.default_clearer')
            ->public()

        ->alias(CacheItemPoolInterface::class, 'cache.app')

        ->alias(CacheInterface::class, 'cache.app')

        ->alias(TagAwareCacheInterface::class, 'cache.app.taggable')
    ;
};
