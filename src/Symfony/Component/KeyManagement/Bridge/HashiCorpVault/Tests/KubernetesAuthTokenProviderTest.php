<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\KubernetesAuthTokenProvider;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class KubernetesAuthTokenProviderTest extends TestCase
{
    private string $jwtPath;

    protected function setUp(): void
    {
        $this->jwtPath = tempnam(sys_get_temp_dir(), 'sf_vault_jwt_');
        file_put_contents($this->jwtPath, "service-account.jwt\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->jwtPath);
    }

    public function testLogsInWithTheRoleAndTheServiceAccountToken()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(['auth' => ['client_token' => 'hvs.token', 'lease_duration' => 3600]]));
        }, 'https://vault.local/v1/');

        $provider = new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath);

        $this->assertSame('hvs.token', $provider->getToken());
        $this->assertSame([
            'POST',
            'https://vault.local/v1/auth/kubernetes/login',
            ['role' => 'app-role', 'jwt' => 'service-account.jwt'],
        ], $captured);
    }

    public function testTokenIsCachedAcrossCalls()
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['auth' => ['client_token' => 'cached', 'lease_duration' => 3600]])), 'https://vault.local/v1/');

        $provider = new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath);
        $provider->getToken();
        $provider->getToken();

        $this->assertSame(1, $client->getRequestsCount());
    }

    public function testOnlyTheMatchingTokenIsInvalidatedAndTheRotatedServiceAccountTokenIsReadAtTheNextLogin()
    {
        $sentJwts = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentJwts): MockResponse {
            $sentJwts[] = json_decode($options['body'], true)['jwt'];

            return new MockResponse(json_encode(['auth' => ['client_token' => 'T'.\count($sentJwts), 'lease_duration' => 3600]]));
        }, 'https://vault.local/v1/');

        $provider = new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath);
        $this->assertSame('T1', $provider->getToken());

        file_put_contents($this->jwtPath, 'rotated.jwt');
        $provider->invalidateToken('older');
        $this->assertSame('T1', $provider->getToken());

        $provider->invalidateToken('T1');
        $this->assertSame('T2', $provider->getToken());
        $this->assertSame(['service-account.jwt', 'rotated.jwt'], $sentJwts);
    }

    public static function provideLeases(): iterable
    {
        yield 'refreshed a margin before a long lease ends' => [3600, 3539, 3541];
        yield 'refreshed halfway through a short lease' => [20, 9, 11];
    }

    #[DataProvider('provideLeases')]
    #[Group('time-sensitive')]
    public function testTokenIsRefreshedBeforeItsLeaseEnds(int $leaseDuration, int $stillCachedAfter, int $refreshedAfter)
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['auth' => ['client_token' => 'T', 'lease_duration' => $leaseDuration]])), 'https://vault.local/v1/');

        $provider = new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath);
        $provider->getToken();

        sleep($stillCachedAfter);
        $provider->getToken();
        $this->assertSame(1, $client->getRequestsCount());

        sleep($refreshedAfter - $stillCachedAfter);
        $provider->getToken();
        $this->assertSame(2, $client->getRequestsCount());
    }

    #[Group('time-sensitive')]
    public function testTokenWithoutLeaseIsCachedUntilInvalidated()
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['auth' => ['client_token' => 'T', 'lease_duration' => 0]])), 'https://vault.local/v1/');

        $provider = new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath);
        $provider->getToken();
        sleep(10 * 365 * 86400);
        $provider->getToken();

        $this->assertSame(1, $client->getRequestsCount());
    }

    public function testRejectedLoginReportsTheVaultErrors()
    {
        $client = new MockHttpClient(
            new MockResponse(json_encode(['errors' => ['invalid role name "app-role"', ['nested']]]), ['http_code' => 400]),
            'https://vault.local/v1/',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vault Kubernetes login failed (HTTP 400): "invalid role name "app-role"".');
        (new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath))->getToken();
    }

    public static function provideBrokenLoginResponses(): iterable
    {
        yield 'transport failure' => [new MockResponse('', ['error' => 'Connection refused.']), 'Failed to reach Vault.'];
        yield 'transport failure while reading the body' => [new MockResponse((static function (): \Generator {
            yield '{"au';
            throw new TransportException('Connection reset.');
        })()), 'Failed to read the Vault login response (HTTP 200)'];
        yield 'non-JSON body' => [new MockResponse('<html>'), 'non-JSON'];
        yield 'missing auth' => [new MockResponse(json_encode(['data' => []])), 'malformed'];
        yield 'empty client token' => [new MockResponse(json_encode(['auth' => ['client_token' => '', 'lease_duration' => 60]])), 'malformed'];
        yield 'non-integer lease' => [new MockResponse(json_encode(['auth' => ['client_token' => 'T', 'lease_duration' => '60']])), 'malformed'];
        yield 'negative lease' => [new MockResponse(json_encode(['auth' => ['client_token' => 'T', 'lease_duration' => -1]])), 'malformed'];
        yield 'redirect' => [new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => 'https://elsewhere.local/']]), 'HTTP 307'];
    }

    #[DataProvider('provideBrokenLoginResponses')]
    public function testBrokenLoginResponsesSurfaceAsRuntimeException(MockResponse $response, string $message)
    {
        $client = new MockHttpClient($response, 'https://vault.local/v1/');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath))->getToken();
    }

    public function testMissingServiceAccountTokenIsReportedWithoutLoggingIn()
    {
        $client = new MockHttpClient([], 'https://vault.local/v1/');
        $missing = $this->jwtPath.'.missing';

        try {
            (new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $missing))->getToken();
            $this->fail('The missing file should be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame(\sprintf('Cannot read the Kubernetes service-account token from "%s".', $missing), $e->getMessage());
        }

        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testEmptyServiceAccountTokenIsReportedWithoutLoggingIn()
    {
        file_put_contents($this->jwtPath, "\n");
        $client = new MockHttpClient([], 'https://vault.local/v1/');

        try {
            (new KubernetesAuthTokenProvider($client, 'app-role', 'kubernetes', $this->jwtPath))->getToken();
            $this->fail('The empty file should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('is empty', $e->getMessage());
        }

        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testCustomMountPointAndNamespace()
    {
        $captured = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [$url, $options['headers']];

            return new MockResponse(json_encode(['auth' => ['client_token' => 'T', 'lease_duration' => 60]]));
        }, 'https://vault.local/v1/');

        (new KubernetesAuthTokenProvider($client, 'app-role', 'k8s/prod cluster', $this->jwtPath, 'tenant-acme'))->getToken();

        [$url, $headers] = $captured;
        $this->assertSame('https://vault.local/v1/auth/k8s/prod%20cluster/login', $url);
        $this->assertContains('X-Vault-Namespace: tenant-acme', $headers);
    }
}
