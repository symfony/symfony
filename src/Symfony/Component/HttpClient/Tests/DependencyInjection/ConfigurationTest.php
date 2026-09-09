<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpClient\HttpClientBundle;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $this->assertSame(['enabled' => true, 'scoped_clients' => []], $this->process([]));
    }

    public function testScopedClientsInheritRateLimiterAndRetryFailedConfiguration()
    {
        $config = $this->process([
            'default_options' => ['rate_limiter' => 'default_limiter', 'retry_failed' => ['max_retries' => 77]],
            'scoped_clients' => [
                'foo' => ['base_uri' => 'http://example.com'],
                'bar' => ['base_uri' => 'http://example.com', 'rate_limiter' => true, 'retry_failed' => true],
                'baz' => ['base_uri' => 'http://example.com', 'rate_limiter' => false, 'retry_failed' => false],
                'qux' => ['base_uri' => 'http://example.com', 'rate_limiter' => 'foo_limiter', 'retry_failed' => ['max_retries' => 88, 'delay' => 999]],
            ],
        ]);

        $scopedClients = $config['scoped_clients'];

        $this->assertSame('default_limiter', $scopedClients['foo']['rate_limiter']);
        $this->assertTrue($scopedClients['foo']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['foo']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['foo']['retry_failed']['delay']);

        $this->assertSame('default_limiter', $scopedClients['bar']['rate_limiter']);
        $this->assertTrue($scopedClients['bar']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['bar']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['bar']['retry_failed']['delay']);

        $this->assertNull($scopedClients['baz']['rate_limiter']);
        $this->assertFalse($scopedClients['baz']['retry_failed']['enabled']);
        $this->assertSame(3, $scopedClients['baz']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['baz']['retry_failed']['delay']);

        $this->assertSame('foo_limiter', $scopedClients['qux']['rate_limiter']);
        $this->assertTrue($scopedClients['qux']['retry_failed']['enabled']);
        $this->assertSame(88, $scopedClients['qux']['retry_failed']['max_retries']);
        $this->assertSame(999, $scopedClients['qux']['retry_failed']['delay']);
    }

    public function testAScopedClientNeedsAScopeOrABaseUri()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Either "scope" or "base_uri" should be defined.');

        $this->process(['scoped_clients' => ['foo' => ['timeout' => 3]]]);
    }

    public function testRetryBaseUrisRequireRetriesToBeEnabled()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "base_uris" option requires retries to be enabled.');

        $this->process(['default_options' => ['retry_failed' => ['enabled' => false, 'base_uris' => ['http://example.com']]]]);
    }

    private function process(mixed $config): array
    {
        return new Processor()->processConfiguration(new Configuration(new HttpClientBundle(), null, 'http_client'), [$config]);
    }
}
