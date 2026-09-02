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
use Jose\Component\Core\AlgorithmManager;
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
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcJwks;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The token handler decodes and validates the token, and retrieves the user identifier from it.
 */
final class OidcTokenHandler implements AccessTokenHandlerInterface, ResetInterface
{
    use OidcTrait;
    private ?JWKSet $decryptionKeyset = null;
    private ?AlgorithmManager $decryptionAlgorithms = null;
    private bool $enforceEncryption = false;

    private bool $enforceKeyUsageVerification = true;
    private ?CacheInterface $discoveryCache = null;
    private ?string $oidcConfigurationCacheKey = null;

    /**
     * @var HttpClientInterface[]
     */
    private array $discoveryClients = [];

    /**
     * @var OidcDiscovery[]
     */
    private array $discoveries = [];

    public function __construct(
        private AlgorithmManager $signatureAlgorithm,
        private ?JWKSet $signatureKeyset,
        private string $audience,
        private array $issuers,
        private string $claim = 'sub',
        private ?LoggerInterface $logger = null,
        private ClockInterface $clock = new Clock(),
        private int $allowedTimeDrift = 0,
    ) {
    }

    public function enableJweSupport(JWKSet $decryptionKeyset, AlgorithmManager $decryptionAlgorithms, bool $enforceEncryption): void
    {
        $this->decryptionKeyset = $decryptionKeyset;
        $this->decryptionAlgorithms = $decryptionAlgorithms;
        $this->enforceEncryption = $enforceEncryption;
    }

    /**
     * @param HttpClientInterface|HttpClientInterface[] $client
     * @param bool                                      $enforceKeyUsageVerification When true (default, strict), only JWKs whose `use` is "sig" or whose
     *                                                                               `key_ops` contains "sign"/"verify" are accepted for signature verification.
     *                                                                               When false (lax), JWKs missing both `use` and `key_ops` are also accepted;
     *                                                                               JWKs explicitly scoped to encryption (`use=enc` or only encryption-related
     *                                                                               `key_ops`) are still rejected. Use the lax mode only with providers known
     *                                                                               to omit `use`/`key_ops` on signing keys.
     */
    public function enableDiscovery(CacheInterface $cache, array|HttpClientInterface $client, string $oidcConfigurationCacheKey, bool $enforceKeyUsageVerification = true): void
    {
        $this->discoveryCache = $cache;
        $this->discoveryClients = \is_array($client) ? $client : [$client];
        $this->oidcConfigurationCacheKey = $oidcConfigurationCacheKey;
        $this->enforceKeyUsageVerification = $enforceKeyUsageVerification;

        // the discovery documents get their own cache entries: $oidcConfigurationCacheKey
        // keeps holding the JWKS, whose lifetime is driven by the JWKS response headers
        $discoveries = [];
        foreach ($this->discoveryClients as $i => $discoveryClient) {
            // the keys are kept aligned with $discoveryClients, which computeDiscoveryKeys() indexes back into
            $discoveries[$i] = new OidcDiscovery($discoveryClient, $cache, cacheKey: $oidcConfigurationCacheKey.'.document.'.$i, checkedEndpoints: ['jwks_uri']);
        }
        $this->discoveries = $discoveries;
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot use the "oidc" token handler since "web-token/jwt-signature" and "web-token/jwt-checker" are not installed. Try running "composer require web-token/jwt-signature web-token/jwt-checker".');
        }

        if (!$this->discoveryClients && !$this->signatureKeyset) {
            throw new \LogicException('You cannot use the "oidc" token handler without JWKSet nor "discovery". Please configure JWKSet in the constructor, or call "enableDiscovery" method.');
        }

        $jwkset = $this->signatureKeyset;
        if ($this->discoveryClients) {
            $keys = $this->discoveryCache->get($this->oidcConfigurationCacheKey, [$this, 'computeDiscoveryKeys']);

            $jwkset = JWKSet::createFromKeyData(['keys' => $keys]);
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
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    /**
     * Computes the JWKS and sets the cache item TTL from provider headers.
     *
     * The cache entry lifetime is automatically adjusted based on the lowest TTL
     * advertised by the providers (via "Cache-Control: max-age" or "Expires" headers).
     *
     * @internal this method is public to enable async offline cache population
     */
    public function computeDiscoveryKeys(ItemInterface $item): array
    {
        if (!$this->discoveries) {
            throw new \LogicException('No OIDC discovery client configured.');
        }
        $logger = $this->logger;
        try {
            $discoveredKeys = [];
            $minTtl = null;
            $jwkSetResponses = [];

            // the ".well-known" requests are sent first, so that they travel concurrently:
            // the responses are lazy, and only consumed by getConfiguration() below
            foreach ($this->discoveries as $discovery) {
                $discovery->prefetch();
            }

            foreach ($this->discoveries as $i => $discovery) {
                // the scheme was checked against the URL that served the document before
                // the configuration was cached, so only the announcement is enforced here
                $jwksUri = self::checkDiscoveredEndpoint($discovery->getConfiguration()['jwks_uri'] ?? null, 'jwks_uri', null);

                $jwkSetResponses[] = $this->discoveryClients[$i]->request('GET', $jwksUri);
            }

            foreach ($jwkSetResponses as $response) {
                [$keys, $currentTtl] = OidcJwks::fromResponse($response, $this->enforceKeyUsageVerification);

                // Apply the lowest TTL found to ensure all keys in the set are still valid
                if (null !== $currentTtl && (null === $minTtl || $currentTtl < $minTtl)) {
                    $minTtl = $currentTtl;
                }

                foreach ($keys as $key) {
                    $discoveredKeys[] = $key;
                }
            }

            if (0 < ($minTtl ?? -1)) {
                $item->expiresAfter(min($minTtl, OidcJwks::MAX_TTL));
            }

            return $discoveredKeys;
        } catch (\Exception $e) {
            $logger?->error('An error occurred while requesting OIDC certs.', [
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
        if (method_exists($jwsVerifier, 'verify')) { // web-token/jwt-library >= 4.3
            $verified = $jwsVerifier->verify($jws, $jwkset, 0)->isVerified();
        } else {
            $verified = $jwsVerifier->verifyWithKeySet($jws, $jwkset, 0);
        }
        if (!$verified) {
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
            new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\NotBeforeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\AudienceChecker($this->audience),
            new Checker\IssuerChecker($this->issuers),
        ];
        $claimCheckerManager = new ClaimCheckerManager($checkers);

        // if this check fails, an InvalidClaimException is thrown
        return $claimCheckerManager->check($claims, ['iat', 'exp', 'aud', 'iss']);
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
                new Checker\CallableChecker('cty', static fn ($value) => 'JWT' === $value),
                new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift, protectedHeaderOnly: true),
                new Checker\NotBeforeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift, protectedHeaderOnly: true),
                new Checker\ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift, protectedHeaderOnly: true),
            ],
            [new JWETokenSupport()]
        );
        $jweDecrypter = new JWEDecrypter($this->decryptionAlgorithms, null);
        $serializerManager = new JWESerializerManager([new JweCompactSerializer()]);
        try {
            $jwe = $serializerManager->unserialize($accessToken);
            $jweHeaderChecker->check($jwe, 0);
            if (method_exists($jweDecrypter, 'decrypt')) { // web-token/jwt-library >= 4.3
                $result = $jweDecrypter->decrypt($jwe, $this->decryptionKeyset, 0);
                $jwe = $result->getJwe();
                $result = $result->isDecrypted();
            } else {
                $result = $jweDecrypter->decryptUsingKeySet($jwe, $this->decryptionKeyset, 0);
            }
            if (!$result) {
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

    public function reset(): void
    {
        foreach ($this->discoveries as $discovery) {
            $discovery->reset();
        }
    }
}
