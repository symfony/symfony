<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheBundle;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'prefix_seed' => '_%kernel.project_dir%.%kernel.container_class%',
            'app' => 'cache.adapter.filesystem',
            'system' => 'cache.adapter.system',
            'directory' => '%kernel.share_dir%/pools/app',
            'default_redis_provider' => 'redis://localhost',
            'default_valkey_provider' => 'valkey://localhost',
            'default_memcached_provider' => 'memcached://localhost',
            'default_doctrine_dbal_provider' => 'database_connection',
            'default_pdo_provider' => null,
            'default_mongodb_provider' => 'mongodb://localhost/app',
            'pools' => [],
        ], $this->process([]));
    }

    public function testAppAndDefaultProviderCannotBeCombined()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "cache.app" and "cache.default_provider" options cannot be used together, the adapter is deduced from the DSN.');

        $this->process(['app' => 'cache.adapter.redis', 'default_provider' => 'redis://localhost']);
    }

    public function testAppAndDefaultProviderCannotBeCombinedAcrossFiles()
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration($this->configuration(), [
            ['app' => 'cache.adapter.redis'],
            ['default_provider' => 'redis://localhost'],
        ]);
    }

    public function testAppSetToItsDefaultValueStillConflictsWithDefaultProvider()
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['app' => 'cache.adapter.filesystem', 'default_provider' => 'redis://localhost']);
    }

    public function testDefaultProviderAloneIsAllowed()
    {
        $this->assertSame('redis://localhost', $this->process(['default_provider' => 'redis://localhost'])['default_provider']);
    }

    public function testAppAloneIsAllowed()
    {
        $this->assertSame('cache.adapter.redis', $this->process(['app' => 'cache.adapter.redis'])['app']);
    }

    public function testAppAndSystemAreReservedPoolNames()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"cache.app" and "cache.system" are reserved names');

        $this->process(['pools' => ['cache.app' => ['adapters' => ['cache.adapter.array']]]]);
    }

    private function process(mixed $config): array
    {
        return (new Processor())->processConfiguration($this->configuration(), [$config]);
    }

    private function configuration(): Configuration
    {
        return new Configuration(new CacheBundle(), null, 'cache');
    }
}
