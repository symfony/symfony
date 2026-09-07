<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Lock\Store\SemaphoreStore;

class ConfigurationTest extends TestCase
{
    #[DataProvider('provideValidLockConfigurationTests')]
    public function testValidLockConfiguration($lockConfig, $processedConfig)
    {
        $this->assertEquals($processedConfig, $this->process($lockConfig));
    }

    public static function provideValidLockConfigurationTests(): iterable
    {
        $defaultStore = SemaphoreStore::isSupported() ? 'semaphore' : 'flock';

        yield [null, ['enabled' => true, 'resources' => ['default' => [$defaultStore]]]];
        yield [[], ['enabled' => true, 'resources' => ['default' => [$defaultStore]]]];
        yield [true, ['enabled' => true, 'resources' => ['default' => [$defaultStore]]]];
        yield [false, ['enabled' => false, 'resources' => ['default' => [$defaultStore]]]];
        yield [['enabled' => false], ['enabled' => false, 'resources' => []]];

        yield ['flock', ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['flock', 'semaphore'], ['enabled' => true, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['foo' => 'flock', 'bar' => 'semaphore'], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore'], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['default' => 'flock'], ['enabled' => true, 'resources' => ['default' => ['flock']]]];

        yield [['enabled' => false, 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'foo' => 'flock', 'bar' => 'semaphore'], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'foo' => ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'default' => 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];

        yield [['resources' => 'flock'], ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['resources' => ['flock', 'semaphore']], ['enabled' => true, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['resources' => ['foo' => 'flock', 'bar' => 'semaphore']], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['resources' => ['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore']], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['resources' => ['default' => 'flock']], ['enabled' => true, 'resources' => ['default' => ['flock']]]];

        yield [['enabled' => false, 'resources' => 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, 'resources' => ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'resources' => ['foo' => 'flock', 'bar' => 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resources' => ['default' => 'flock']], ['enabled' => false, 'resources' => ['default' => ['flock']]]];

        // xml

        yield [['resource' => ['flock']], ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['resource' => ['flock', ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['default' => ['flock'], 'foo' => ['semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock']]], ['enabled' => true, 'resources' => ['foo' => ['flock']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];

        yield [['enabled' => false, 'resource' => ['flock']], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, 'resource' => ['flock', ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['default' => ['flock'], 'foo' => ['semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock']]], ['enabled' => false, 'resources' => ['foo' => ['flock']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];

        // service id and advisory locks

        $advisory = ['service_id' => 'my_connection', 'advisory' => true];
        $tableBased = ['service_id' => 'my_connection', 'advisory' => false];

        yield [['service_id' => 'my_connection'], ['enabled' => true, 'resources' => ['default' => [$tableBased]]]];
        yield [$advisory, ['enabled' => true, 'resources' => ['default' => [$advisory]]]];
        yield [['advisory' => true, 'service_id' => 'my_connection'], ['enabled' => true, 'resources' => ['default' => [$advisory]]]];
        yield [[$advisory], ['enabled' => true, 'resources' => ['default' => [$advisory]]]];
        yield [['flock', $advisory], ['enabled' => true, 'resources' => ['default' => ['flock', $advisory]]]];
        yield [['foo' => $advisory], ['enabled' => true, 'resources' => ['foo' => [$advisory]]]];
        yield [['foo' => [$advisory]], ['enabled' => true, 'resources' => ['foo' => [$advisory]]]];
        yield [['foo' => ['flock', $advisory], 'bar' => 'semaphore'], ['enabled' => true, 'resources' => ['foo' => ['flock', $advisory], 'bar' => ['semaphore']]]];
        yield [['resources' => $advisory], ['enabled' => true, 'resources' => ['default' => [$advisory]]]];
        yield [['resources' => ['foo' => $advisory]], ['enabled' => true, 'resources' => ['foo' => [$advisory]]]];
        yield [['resources' => ['foo' => ['flock', $advisory]]], ['enabled' => true, 'resources' => ['foo' => ['flock', $advisory]]]];
        yield [['enabled' => false, 'foo' => $advisory], ['enabled' => false, 'resources' => ['foo' => [$advisory]]]];
    }

    #[DataProvider('provideInvalidLockConfigurationTests')]
    public function testInvalidLockConfiguration(array $lockConfig, string $expectedMessage)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->process($lockConfig);
    }

    public static function provideInvalidLockConfigurationTests(): iterable
    {
        yield [
            ['foo' => ['advisory' => true]],
            'Invalid configuration for path "lock.resources.foo.0": A lock store must be a string or an array with a "service_id" string and an optional "advisory" boolean, got {"service_id":null,"advisory":true}.',
        ];

        yield [
            ['foo' => ['service_id' => 'my_connection', 'advisory' => 'yes']],
            'Invalid configuration for path "lock.resources.foo.0": A lock store must be a string or an array with a "service_id" string and an optional "advisory" boolean, got {"service_id":"my_connection","advisory":"yes"}.',
        ];

        yield [
            ['foo' => ['service_id' => 'my_connection', 'store' => 'postgresql_advisory']],
            'Invalid configuration for path "lock.resources.foo.0": A lock store must be a string or an array with a "service_id" string and an optional "advisory" boolean, got {"service_id":"my_connection","advisory":false,"store":"postgresql_advisory"}.',
        ];
    }

    public function testLockMergeConfigs()
    {
        $config = (new Processor())->processConfiguration($this->configuration(), [
            ['payload' => 'flock'],
            ['payload' => 'semaphore'],
        ]);

        $this->assertEquals(['enabled' => true, 'resources' => ['payload' => ['semaphore']]], $config);
    }

    public function testLockCanBeDisabled()
    {
        $this->assertFalse($this->process(['enabled' => false])['enabled']);
    }

    public function testEnabledLockNeedsResources()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "lock": At least one resource must be defined.');

        $this->process(['enabled' => true]);
    }

    private function process(mixed $config): array
    {
        return (new Processor())->processConfiguration($this->configuration(), [$config]);
    }

    private function configuration(): Configuration
    {
        return new Configuration(new LockBundle(), null, 'lock');
    }
}
