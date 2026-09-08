<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\RateLimiter\RateLimiterBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame([
            'limiters' => [],
            'enabled' => true,
            'builder' => [
                'lock_factory' => 'auto',
                'cache_pool' => 'cache.rate_limiter',
                'storage_service' => null,
            ],
        ], $this->process([]));
    }

    public function testLimitersCanBeDeclaredWithoutTheLimitersKey()
    {
        $config = $this->process([
            'foo' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
        ]);

        $this->assertSame(['foo'], array_keys($config['limiters']));
        $this->assertTrue($config['enabled']);
    }

    public function testTheBuilderIsNotReadAsALimiterName()
    {
        // no "limiters" key: the shorthand would otherwise read "builder" as a limiter name
        $config = $this->process([
            'builder' => ['cache_pool' => 'my.pool'],
        ]);

        $this->assertSame([], $config['limiters']);
        $this->assertSame('my.pool', $config['builder']['cache_pool']);
    }

    public function testTheEnabledKeyIsNotReadAsALimiterName()
    {
        $config = $this->process([
            'enabled' => false,
            'foo' => ['policy' => 'no_limit'],
        ]);

        $this->assertFalse($config['enabled']);
        $this->assertSame(['foo'], array_keys($config['limiters']));
    }

    public function testTheBuilderCanBeConfiguredAlongsideLimiters()
    {
        $config = $this->process([
            'limiters' => ['foo' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute']],
            'builder' => ['cache_pool' => 'my.pool'],
        ]);

        $this->assertSame(['foo'], array_keys($config['limiters']));
        $this->assertSame('my.pool', $config['builder']['cache_pool']);
    }

    public function testALimitIsRequired()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A limit must be provided when using a policy different than "compound" or "no_limit".');

        $this->process(['foo' => ['policy' => 'fixed_window', 'interval' => '1 minute']]);
    }

    public function testAnchorAtRequiresTheFixedWindowPolicy()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "anchor_at" option is only supported with the "fixed_window" policy.');

        $this->process(['foo' => ['policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 month', 'anchor_at' => '2024-01-05 00:00:00 UTC']]);
    }

    public function testAnchorAtRequiresAMonthlyOrYearlyInterval()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "anchor_at" option requires an "interval" of at least one month.');

        $this->process(['foo' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour', 'anchor_at' => '2024-01-05 00:00:00 UTC']]);
    }

    private function process(mixed $config): array
    {
        return new Processor()->processConfiguration(new Configuration(new RateLimiterBundle(), null, 'rate_limiter'), [$config]);
    }
}
