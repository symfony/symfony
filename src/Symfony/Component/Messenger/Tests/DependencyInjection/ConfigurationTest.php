<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Messenger\MessengerBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'enabled' => true,
            'routing' => [],
            'serializer' => [
                'default_serializer' => 'messenger.transport.native_php_serializer',
                'symfony_serializer' => [
                    'format' => 'json',
                    'context' => [],
                ],
            ],
            'transports' => [],
            'failure_transport' => null,
            'stop_worker_on_signals' => [],
            'reject_redelivered_messages' => true,
            'default_bus' => null,
            'buses' => ['messenger.bus.default' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true], 'middleware' => []]],
        ], $this->process([]));
    }

    public function testItShowANiceMessageIfTwoBusesAreConfiguredButNoDefaultBus()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You must specify the "default_bus" if you define more than one bus.');

        $this->process([
            'default_bus' => null,
            'buses' => [
                'first_bus' => [],
                'second_bus' => [],
            ],
        ]);
    }

    public function testItErrorsWhenDefaultBusDoesNotExist()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The specified default bus "foo" is not configured. Available buses are "bar", "baz".');

        $this->process([
            'default_bus' => 'foo',
            'buses' => [
                'bar' => null,
                'baz' => null,
            ],
        ]);
    }

    public function testClaimCheckConfiguration()
    {
        $config = $this->process([
            'transports' => [
                'async' => [
                    'dsn' => 'in-memory:///',
                    'claim_check' => [
                        'cache_pool' => 'app.claim_check_pool',
                        'max_size' => 200000,
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'cache_pool' => 'app.claim_check_pool',
            'max_size' => 200000,
        ], $config['transports']['async']['claim_check']);
    }

    public function testBusMiddlewareDontMerge()
    {
        $config = new Processor()->processConfiguration($this->configuration(), [
            [
                'default_bus' => 'existing_bus',
                'buses' => [
                    'existing_bus' => [
                        'middleware' => 'existing_bus.middleware',
                    ],
                    'common_bus' => [
                        'default_middleware' => false,
                        'middleware' => 'common_bus.old_middleware',
                    ],
                ],
            ],
            [
                'buses' => [
                    'common_bus' => [
                        'middleware' => 'common_bus.new_middleware',
                    ],
                    'new_bus' => [
                        'middleware' => 'new_bus.middleware',
                    ],
                ],
            ],
        ]);

        $this->assertEquals(
            [
                'existing_bus' => [
                    'default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'existing_bus.middleware', 'arguments' => []],
                    ],
                ],
                'common_bus' => [
                    'default_middleware' => ['enabled' => false, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'common_bus.new_middleware', 'arguments' => []],
                    ],
                ],
                'new_bus' => [
                    'default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'new_bus.middleware', 'arguments' => []],
                    ],
                ],
            ],
            $config['buses']
        );
    }

    private function process(mixed $config): array
    {
        return new Processor()->processConfiguration($this->configuration(), [$config]);
    }

    private function configuration(): Configuration
    {
        return new Configuration(new MessengerBundle(), null, 'messenger');
    }
}
