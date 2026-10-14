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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\KubernetesAuthTokenProvider;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\StaticTokenProvider;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TokenProviderInterface;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKms;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKmsFactory;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Runs against a Vault provisioned like a production one by Fixtures/vault/provision.sh.
 *
 * The application side only ever gets a token restricted to the provisioned policy;
 * the operator token is used to issue and revoke such tokens, like an operator would.
 *
 *     docker run -d --name vault --cap-add=IPC_LOCK -p 8200:8200 -e VAULT_DEV_ROOT_TOKEN_ID=root \
 *         -v "$PWD/src/Symfony/Component/KeyManagement/Bridge/HashiCorpVault/Tests/Fixtures/vault:/fixtures:ro" hashicorp/vault:2.1.1
 *     docker exec -e VAULT_TOKEN=root vault sh /fixtures/provision.sh
 *     HASHICORP_VAULT_TEST_ADDR=http://localhost:8200 HASHICORP_VAULT_TEST_TOKEN=root ./phpunit --group integration src/Symfony/Component/KeyManagement/Bridge/HashiCorpVault
 *
 * Dedicated variables are used rather than VAULT_ADDR and VAULT_TOKEN, so that a token
 * meant for a real Vault is never picked up by accident.
 */
#[Group('integration')]
final class TransitKmsIntegrationTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/Fixtures/vault';
    private const string KEY = 'symfony-ci';
    private const string DERIVED_KEY = 'symfony-ci-derived';
    private const string POLICY = 'symfony-kms';
    private const string POLICY_WITHOUT_KEY_READ = 'symfony-kms-without-key-read';
    private const string ROLE = 'symfony-app';

    private HttpClientInterface $client;
    private HttpClientInterface $operator;

    protected function setUp(): void
    {
        if (!$address = getenv('HASHICORP_VAULT_TEST_ADDR')) {
            $this->markTestSkipped('Set HASHICORP_VAULT_TEST_ADDR and HASHICORP_VAULT_TEST_TOKEN to run the tests against a live Vault.');
        }

        $this->client = HttpClient::createForBaseUri(rtrim($address, '/').'/v1/');
        $this->operator = $this->client->withOptions(['headers' => ['X-Vault-Token' => (string) getenv('HASHICORP_VAULT_TEST_TOKEN')]]);

        if (404 === $this->operator->request('GET', 'sys/policies/acl/'.self::POLICY)->getStatusCode()) {
            $this->fail('Vault is not provisioned: run Tests/Fixtures/vault/provision.sh against it first.');
        }
    }

    public function testTheProvisionedKeyIsTheCommittedOneAndCannotLeaveVault()
    {
        $key = $this->operatorRequest('GET', 'transit/keys/'.self::KEY)['data'];

        $this->assertTrue($key['imported_key']);
        $this->assertFalse($key['exportable']);
        $this->assertFalse($key['deletion_allowed']);
        $this->assertSame('aes256-gcm96', $key['type']);

        $ciphertext = $this->transit($this->appToken())->encrypt(self::KEY, 'hello');

        $this->assertSame('hello', self::decryptLocally($ciphertext, base64_decode(file_get_contents(self::FIXTURES.'/transit-key.b64'))));
    }

    public function testRoundTripThroughTheDsnWithAnApplicationToken()
    {
        $address = parse_url((string) getenv('HASHICORP_VAULT_TEST_ADDR'));
        $dsn = \sprintf('hashicorp-vault-transit://%s@%s:%d/v1/?scheme=%s', $this->appToken(), $address['host'], $address['port'] ?? 8200, $address['scheme']);
        $kms = (new TransitKmsFactory())->create(Dsn::fromString($dsn));

        $ciphertext = $kms->encrypt(self::KEY, 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));

        $dataKey = $kms->generateDataKey(self::KEY);
        $this->assertSame(
            $dataKey->use(static fn (string $key): string => $key),
            $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key),
        );
    }

    public function testCiphertextsWrittenBeforeStillDecrypt()
    {
        $kms = $this->transit($this->appToken());

        foreach (json_decode(file_get_contents(self::FIXTURES.'/ciphertexts.json'), true, flags: \JSON_THROW_ON_ERROR) as $name => $fixture) {
            $this->assertSame($fixture['plaintext'], $kms->decrypt(new Ciphertext($fixture['ciphertext'], $fixture['key']), $fixture['aad']), $name);
        }
    }

    public function testAadIsRefusedOnANonDerivedKey()
    {
        $this->expectException(UnsupportedOperationException::class);
        $this->transit($this->appToken())->encrypt(self::KEY, 'hello', 'tenant-a');
    }

    public function testAadIsEnforcedOnADerivedKey()
    {
        $kms = $this->transit($this->appToken());
        $ciphertext = $kms->encrypt(self::DERIVED_KEY, 'hello', 'tenant-a');

        $this->assertSame('hello', $kms->decrypt($ciphertext, 'tenant-a'));

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext, 'tenant-b');
    }

    public function testRevokedTokenIsReplacedAndTheRequestRetried()
    {
        $tokens = new class(fn (): string => $this->appToken()) implements TokenProviderInterface {
            public ?string $token = null;
            public int $issued = 0;

            public function __construct(private readonly \Closure $issue)
            {
            }

            public function getToken(): string
            {
                ++$this->issued;

                return $this->token ??= ($this->issue)();
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
                if ($this->token === $token) {
                    $this->token = null;
                }
            }
        };
        $kms = $this->transit($tokens);

        $ciphertext = $kms->encrypt(self::KEY, 'hello');
        $this->operatorRequest('POST', 'auth/token/revoke', ['token' => $tokens->token]);

        $this->assertSame('hello', $kms->decrypt($ciphertext));
        $this->assertSame(3, $tokens->issued);
    }

    public function testMissingKeyReadCapabilityIsReportedWithoutRetry()
    {
        $tokens = new class($this->appToken(self::POLICY_WITHOUT_KEY_READ)) implements TokenProviderInterface {
            public int $invalidated = 0;

            public function __construct(#[\SensitiveParameter] private readonly string $token)
            {
            }

            public function getToken(): string
            {
                return $this->token;
            }

            public function invalidateToken(#[\SensitiveParameter] string $token): void
            {
                ++$this->invalidated;
            }
        };

        try {
            $this->transit($tokens)->encrypt(self::DERIVED_KEY, 'hello', 'tenant-a');
            $this->fail('The missing capability should be reported.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertStringContainsString('permission denied', $e->getMessage());
            $this->assertStringNotContainsString('invalid token', $e->getMessage());
        }

        $this->assertSame(0, $tokens->invalidated);
    }

    /**
     * Kubernetes auth checks each login against the cluster's TokenReview API, so the provisioning
     * enables the JWT auth method at the "kubernetes" path instead: it verifies the signature
     * locally and shares the login protocol, so the provider runs with its defaults.
     */
    public function testKubernetesAuthTokenProviderLogsInAgainWithTheRotatedServiceAccountToken()
    {
        $jwtPath = tempnam(sys_get_temp_dir(), 'sf_vault_jwt_');
        try {
            file_put_contents($jwtPath, self::serviceAccountToken('system:serviceaccount:app:first'));
            $tokens = new KubernetesAuthTokenProvider($this->client, self::ROLE, 'kubernetes', $jwtPath);
            $kms = $this->transit($tokens);

            $ciphertext = $kms->encrypt(self::KEY, 'hello');
            $firstToken = $tokens->getToken();

            file_put_contents($jwtPath, self::serviceAccountToken('system:serviceaccount:app:rotated'));
            $this->operatorRequest('POST', 'auth/token/revoke', ['token' => $firstToken]);

            $this->assertSame('hello', $kms->decrypt($ciphertext));
            $this->assertNotSame($firstToken, $secondToken = $tokens->getToken());
            $this->assertSame([self::POLICY], array_values(array_diff($this->operatorRequest('POST', 'auth/token/lookup', ['token' => $secondToken])['data']['policies'], ['default'])));
            $this->assertSame('system:serviceaccount:app:rotated', $this->subjectOf($secondToken));
        } finally {
            @unlink($jwtPath);
        }
    }

    private function transit(TokenProviderInterface|string $tokens): TransitKms
    {
        return new TransitKms($this->client, \is_string($tokens) ? new StaticTokenProvider($tokens) : $tokens);
    }

    private function appToken(string $policy = self::POLICY): string
    {
        return $this->operatorRequest('POST', 'auth/token/create', ['policies' => [$policy], 'no_default_policy' => true, 'ttl' => '5m'])['auth']['client_token'];
    }

    private function subjectOf(#[\SensitiveParameter] string $token): string
    {
        $entityId = $this->operatorRequest('POST', 'auth/token/lookup', ['token' => $token])['data']['entity_id'];

        return $this->operatorRequest('GET', 'identity/entity/id/'.$entityId)['data']['aliases'][0]['name'];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function operatorRequest(string $method, string $path, ?array $body = null): array
    {
        $response = $this->operator->request($method, $path, null === $body ? [] : ['json' => $body]);

        return 204 === $response->getStatusCode() ? [] : $response->toArray();
    }

    /**
     * Decrypts an aes256-gcm96 Transit ciphertext without Vault: "vault:v<n>:" then base64(nonce || ciphertext || tag).
     */
    private static function decryptLocally(Ciphertext $ciphertext, #[\SensitiveParameter] string $key): string
    {
        $raw = base64_decode(explode(':', $ciphertext->blob, 3)[2], true);

        return openssl_decrypt(substr($raw, 12, -16), 'aes-256-gcm', $key, \OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, -16));
    }

    private static function serviceAccountToken(string $subject): string
    {
        $encode = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $input = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode(['sub' => $subject, 'aud' => 'vault', 'iat' => time(), 'exp' => time() + 600]));
        openssl_sign($input, $signature, file_get_contents(self::FIXTURES.'/jwt-signing-key.pem'), \OPENSSL_ALGO_SHA256);

        return $input.'.'.$encode($signature);
    }
}
