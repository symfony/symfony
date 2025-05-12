<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Oidc;

use Jose\Component\Checker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWETokenSupport;
use Jose\Component\Encryption\Serializer\CompactSerializer as JweCompactSerializer;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer as JwsCompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\Oidc\Exception\InvalidSignatureException;
use Symfony\Component\Security\Http\AccessToken\Oidc\Exception\MissingClaimException;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The token handler decodes and validates the token, and retrieves the user identifier from it.
 */
final class OidcTokenHandler implements AccessTokenHandlerInterface
{
    use OidcTrait;
    private ?JWKSet $decryptionKeyset = null;
    private ?AlgorithmManager $decryptionAlgorithms = null;
    private bool $enforceEncryption = false;

    private ?CacheInterface $discoveryCache = null;
    private ?HttpClientInterface $discoveryClient = null;
    private ?string $oidcConfigurationCacheKey = null;
    private ?string $oidcJWKSetCacheKey = null;

    public function __construct(
        private Algorithm|AlgorithmManager $signatureAlgorithm,
        private JWK|JWKSet|null $signatureKeyset,
        private string $audience,
        private array $issuers,
        private string $claim = 'sub',
        private ?LoggerInterface $logger = null,
        private ClockInterface $clock = new Clock(),
    ) {
        if ($signatureAlgorithm instanceof Algorithm) {
            trigger_deprecation('symfony/security-http', '7.1', 'First argument must be instance of %s, %s given.', AlgorithmManager::class, Algorithm::class);
            $this->signatureAlgorithm = new AlgorithmManager([$signatureAlgorithm]);
        }
        if ($signatureKeyset instanceof JWK) {
            trigger_deprecation('symfony/security-http', '7.1', 'Second argument must be instance of %s, %s given.', JWKSet::class, JWK::class);
            $this->signatureKeyset = new JWKSet([$signatureKeyset]);
        }
    }

    public function enableJweSupport(JWKSet $decryptionKeyset, AlgorithmManager $decryptionAlgorithms, bool $enforceEncryption): void
    {
        $this->decryptionKeyset = $decryptionKeyset;
        $this->decryptionAlgorithms = $decryptionAlgorithms;
        $this->enforceEncryption = $enforceEncryption;
    }

    public function enableDiscovery(CacheInterface $cache, HttpClientInterface $client, string $oidcConfigurationCacheKey, string $oidcJWKSetCacheKey): void
    {
        $this->discoveryCache = $cache;
        $this->discoveryClient = $client;
        $this->oidcConfigurationCacheKey = $oidcConfigurationCacheKey;
        $this->oidcJWKSetCacheKey = $oidcJWKSetCacheKey;
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot use the "oidc" token handler since "web-token/jwt-signature" and "web-token/jwt-checker" are not installed. Try running "composer require web-token/jwt-signature web-token/jwt-checker".');
        }

        if (!$this->discoveryCache && !$this->signatureKeyset) {
            throw new \LogicException('You cannot use the "oidc" token handler without JWKSet nor "discovery". Please configure JWKSet in the constructor, or call "enableDiscovery" method.');
        }

        $jwkset = $this->signatureKeyset;
        if ($this->discoveryCache) {
            try {
                $oidcConfiguration = json_decode($this->discoveryCache->get($this->oidcConfigurationCacheKey, function (): string {
                    $response = $this->discoveryClient->request('GET', '.well-known/openid-configuration');

                    return $response->getContent();
                }), true, 512, \JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $this->logger?->error('An error occurred while requesting OIDC configuration.', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
            }

            try {
                $jwkset = JWKSet::createFromJson(
                    $this->discoveryCache->get($this->oidcJWKSetCacheKey, function () use ($oidcConfiguration): string {
                        $response = $this->discoveryClient->request('GET', $oidcConfiguration['jwks_uri']);
                        // we only need signature key
                        $keys = array_filter($response->toArray()['keys'], static fn (array $key) => 'sig' === $key['use']);

                        return json_encode(['keys' => $keys]);
                    })
                );
            } catch (\Throwable $e) {
                $this->logger?->error('An error occurred while requesting OIDC certs.', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
            }
        }

        try {
            $accessToken = $this->decryptIfNeeded($accessToken);
            $claims = $this->loadAndVerifyJws($accessToken, $jwkset);
            $this->verifyClaims($claims);

            if (empty($claims[$this->claim])) {
                throw new MissingClaimException(\sprintf('"%s" claim not found.', $this->claim));
            }

            // UserLoader argument can be overridden by a UserProvider on AccessTokenAuthenticator::authenticate
            return new UserBadge($claims[$this->claim], new FallbackUserLoader(function () use ($claims) {
                $claims['user_identifier'] = $claims[$this->claim];

                return $this->createUser($claims);
            }), $claims);
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred while decoding and validating the token.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    private function loadAndVerifyJws(string $accessToken, JWKSet $jwkset): array
    {
        // Decode the token
        $jwsVerifier = new JWSVerifier($this->signatureAlgorithm);
        $serializerManager = new JWSSerializerManager([new JwsCompactSerializer()]);
        $jws = $serializerManager->unserialize($accessToken);

        // Verify the signature
        if (!$jwsVerifier->verifyWithKeySet($jws, $jwkset, 0)) {
            throw new InvalidSignatureException();
        }

        $headerCheckerManager = new Checker\HeaderCheckerManager([
            new Checker\AlgorithmChecker($this->signatureAlgorithm->list()),
        ], [
            new JWSTokenSupport(),
        ]);
        // if this check fails, an InvalidHeaderException is thrown
        $headerCheckerManager->check($jws, 0);

        return json_decode($jws->getPayload(), true);
    }

    private function verifyClaims(array $claims): array
    {
        // Verify the claims
        $checkers = [
            new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
            new Checker\NotBeforeChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
            new Checker\ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
            new Checker\AudienceChecker($this->audience),
            new Checker\IssuerChecker($this->issuers),
        ];
        $claimCheckerManager = new ClaimCheckerManager($checkers);

        // if this check fails, an InvalidClaimException is thrown
        return $claimCheckerManager->check($claims);
    }

    private function decryptIfNeeded(string $accessToken): string
    {
        if (null === $this->decryptionKeyset || null === $this->decryptionAlgorithms) {
            $this->logger?->debug('The encrypted tokens (JWE) are not supported. Skipping.');

            return $accessToken;
        }

        $jweHeaderChecker = new Checker\HeaderCheckerManager(
            [
                new Checker\AlgorithmChecker($this->decryptionAlgorithms->list()),
                new Checker\CallableChecker('enc', fn ($value) => \in_array($value, $this->decryptionAlgorithms->list())),
                new Checker\CallableChecker('cty', fn ($value) => 'JWT' === $value),
                new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
                new Checker\NotBeforeChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
                new Checker\ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: 0, protectedHeaderOnly: true),
            ],
            [new JWETokenSupport()]
        );
        $jweDecrypter = new JWEDecrypter($this->decryptionAlgorithms, null);
        $serializerManager = new JWESerializerManager([new JweCompactSerializer()]);
        try {
            $jwe = $serializerManager->unserialize($accessToken);
            $jweHeaderChecker->check($jwe, 0);
            $result = $jweDecrypter->decryptUsingKeySet($jwe, $this->decryptionKeyset, 0);
            if (false === $result) {
                throw new \RuntimeException('The JWE could not be decrypted.');
            }

            $payload = $jwe->getPayload();
            if (null === $payload) {
                throw new \RuntimeException('The JWE payload is empty.');
            }

            return $payload;
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($this->enforceEncryption) {
                $this->logger?->error('An error occurred while decrypting the token.', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new BadCredentialsException('Encrypted token is required.', 0, $e);
            }
            $this->logger?->debug('The token decryption failed. Skipping as not mandatory.');

            return $accessToken;
        }
    }
}
