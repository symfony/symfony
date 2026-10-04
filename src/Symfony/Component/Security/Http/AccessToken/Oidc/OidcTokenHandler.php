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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Oidc\OidcProviderKeys;
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

    /**
     * The "typ" header values RFC 9068 §4 accepts for a JWT access token.
     */
    private const AT_JWT_TYPES = ['at+jwt', 'application/at+jwt'];

    private ?JWKSet $decryptionKeyset = null;
    private ?AlgorithmManager $decryptionAlgorithms = null;
    private bool $enforceEncryption = false;

    private bool $enforceAtJwtType;

    /**
     * @var list<string>
     */
    private array $audiences;

    /**
     * The issuers that the discovery documents must announce, or null to accept any allowed one.
     *
     * @var list<string|null>
     */
    private array $discoveryIssuers = [];

    private bool $bindKeysToIssuers = false;

    /**
     * @var OidcDiscovery[]
     */
    private array $discoveries = [];

    /**
     * The signing keys of each provider, kept aligned with $discoveries.
     *
     * @var list<OidcProviderKeys>
     */
    private array $providerKeys = [];

    /**
     * @param string|list<string> $audience         The identifiers of this resource server, one of which the "aud" of
     *                                              the token must name. A resource server answering for several
     *                                              identifiers, as one deployed behind more than one API base URL is,
     *                                              declares them all.
     * @param bool|null           $enforceAtJwtType Whether the "typ" header of the token must be "at+jwt" or "application/at+jwt",
     *                                              which RFC 9068 §4 requires from a JWT access token. This is what tells an access
     *                                              token apart from the ID token the provider issues for the same audience, which
     *                                              would otherwise pass every other check. Turn it off only for providers that do
     *                                              not follow the profile and keep emitting a plain "JWT" type. Defaults to false
     *                                              in 8.2 and to true as of 9.0.
     */
    public function __construct(
        private AlgorithmManager $signatureAlgorithm,
        private ?JWKSet $signatureKeyset,
        string|array $audience,
        private array $issuers,
        private string $claim = 'sub',
        private ?LoggerInterface $logger = null,
        private ClockInterface $clock = new Clock(),
        private int $allowedTimeDrift = 0,
        ?bool $enforceAtJwtType = null,
    ) {
        $audiences = \is_array($audience) ? array_values($audience) : [$audience];

        if (!$audiences) {
            throw new \InvalidArgumentException(\sprintf('The "$audience" argument of "%s()" cannot be an empty list: a resource server that answers for no identifier can accept no token.', __METHOD__));
        }

        foreach ($audiences as $value) {
            if (!\is_string($value) || '' === $value) {
                throw new \InvalidArgumentException(\sprintf('The "$audience" argument of "%s()" must be a non-empty string or a list of non-empty strings.', __METHOD__));
            }
        }

        $this->audiences = $audiences;

        if (null === $enforceAtJwtType) {
            trigger_deprecation('symfony/security-http', '8.2', 'Not passing a value for the "$enforceAtJwtType" argument of "%s()" is deprecated, pass it explicitly; it will default to true in 9.0.', __METHOD__);
        }

        $this->enforceAtJwtType = $enforceAtJwtType ?? false;
    }

    public function enableJweSupport(JWKSet $decryptionKeyset, AlgorithmManager $decryptionAlgorithms, bool $enforceEncryption): void
    {
        $this->decryptionKeyset = $decryptionKeyset;
        $this->decryptionAlgorithms = $decryptionAlgorithms;
        $this->enforceEncryption = $enforceEncryption;
    }

    /**
     * @param HttpClientInterface|HttpClientInterface[] $client                      A client keyed by a string requires the discovery document it fetches to announce that issuer, a trailing slash aside
     * @param string                                    $oidcConfigurationCacheKey   Base cache key, under which each discovery document is cached below a ".document.N" suffix. The signing keys are not cached under it: they go under a key derived from the "jwks_uri" that served them, so that every reader of one provider shares the entry
     * @param bool                                      $enforceKeyUsageVerification When true (default, strict), only JWKs whose `use` is "sig" or whose
     *                                                                               `key_ops` contains "sign"/"verify" are accepted for signature verification.
     *                                                                               When false (lax), JWKs missing both `use` and `key_ops` are also accepted;
     *                                                                               JWKs explicitly scoped to encryption (`use=enc` or only encryption-related
     *                                                                               `key_ops`) are still rejected. Use the lax mode only with providers known
     *                                                                               to omit `use`/`key_ops` on signing keys.
     */
    public function enableDiscovery(CacheInterface $cache, array|HttpClientInterface $client, string $oidcConfigurationCacheKey, bool $enforceKeyUsageVerification = true): void
    {
        $clients = \is_array($client) ? $client : [$client];
        $this->discoveryIssuers = array_map(static fn ($key) => \is_string($key) ? $key : null, array_keys($clients));
        $pinned = array_filter($this->discoveryIssuers, \is_string(...));
        $this->bindKeysToIssuers = 1 < \count($clients) || $pinned;

        // the document and the keys of each provider get their own cache entries, so that the
        // lifetime the provider advertises on each applies to it alone, and so that a reader
        // of one provider never invalidates the keys of another
        $discoveries = [];
        $providerKeys = [];
        foreach (array_values($clients) as $i => $discoveryClient) {
            // the keys are kept aligned, which announcedIssuers() indexes back into
            $discoveries[$i] = new OidcDiscovery($discoveryClient, $cache, '.well-known/openid-configuration', null, 3600, $oidcConfigurationCacheKey.'.document.'.$i, ['jwks_uri']);
            // the "jwks_uri" is checked against the URL that served the document, by the
            // $checkedEndpoints above, rather than required to be HTTPS outright: this handler
            // has always accepted the plain-HTTP key set of a provider served over plain HTTP
            $providerKeys[$i] = new OidcProviderKeys($discoveries[$i], $discoveryClient, $cache, $this->clock, 3600, $enforceKeyUsageVerification, false);
        }
        $this->discoveries = $discoveries;
        $this->providerKeys = $providerKeys;
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot use the "oidc" token handler since "web-token/jwt-signature" and "web-token/jwt-checker" are not installed. Try running "composer require web-token/jwt-signature web-token/jwt-checker".');
        }

        if (!$this->providerKeys && !$this->signatureKeyset) {
            throw new \LogicException('You cannot use the "oidc" token handler without JWKSet nor "discovery". Please configure JWKSet in the constructor, or call "enableDiscovery" method.');
        }

        try {
            $accessToken = $this->decryptIfNeeded($accessToken);
            $claims = $this->loadAndVerifyJws($accessToken);
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
     * Warms the signing keys of every configured provider, indexed by the issuer each announces
     * when the keys are bound to issuers, as the verification reads them.
     *
     * Each provider caches its own keys, so $item is left untouched and only kept for BC.
     *
     * @return list<array<string, mixed>>|array<string, list<array<string, mixed>>>
     *
     * @internal this method is public to enable async offline cache population
     */
    public function computeDiscoveryKeys(ItemInterface $item): array
    {
        if (!$this->providerKeys) {
            throw new \LogicException('No OIDC discovery client configured.');
        }

        try {
            if (!$this->bindKeysToIssuers) {
                return $this->providerKeys[0]->getSignatureKeys();
            }

            $keys = [];
            foreach ($this->announcedIssuers() as $issuer => $i) {
                $keys[$issuer] = $this->providerKeys[$i]->getSignatureKeys();
            }

            return $keys;
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred while requesting OIDC certs.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    /**
     * The three checks are what keeps the keys of one provider from verifying the tokens of
     * another, so every document is read even to resolve one issuer.
     *
     * @return array<string, int>
     */
    private function announcedIssuers(): array
    {
        foreach ($this->providerKeys as $providerKeys) {
            $providerKeys->prefetch();
        }

        $issuers = [];
        foreach ($this->providerKeys as $i => $providerKeys) {
            $issuer = $providerKeys->getAnnouncedIssuer();
            $expectedIssuer = $this->discoveryIssuers[$i];

            if (null !== $expectedIssuer && (null === $issuer || rtrim($issuer, '/') !== rtrim($expectedIssuer, '/'))) {
                throw new \RuntimeException(\sprintf('The OIDC provider announced the issuer "%s", which does not match the expected issuer "%s".', $this->describeAnnouncedIssuer($i), $expectedIssuer));
            }

            if (null === $issuer || !\in_array($issuer, $this->issuers, true)) {
                throw new \RuntimeException(\sprintf('The OIDC provider announced the issuer "%s", which is not allowed.', $this->describeAnnouncedIssuer($i)));
            }

            if (isset($issuers[$issuer])) {
                throw new \RuntimeException(\sprintf('The OIDC issuer "%s" is announced by more than one discovery document.', $issuer));
            }

            $issuers[$issuer] = $i;
        }

        return $issuers;
    }

    /**
     * Names the issuer a document announces, by its type when it is not a string.
     */
    private function describeAnnouncedIssuer(int $i): string
    {
        $issuer = $this->discoveries[$i]->getConfiguration()['issuer'] ?? null;

        return \is_string($issuer) ? $issuer : get_debug_type($issuer);
    }

    /**
     * Nothing of the token is trusted here: the "iss" read from the payload only selects the keys
     * of that very issuer, and the "kid" is only a hint that the provider may have rotated its own.
     *
     * @param array<string, mixed>|mixed $claims
     */
    private function resolveDiscoveredKeySet(?string $kid, mixed $claims): JWKSet
    {
        if (!$this->bindKeysToIssuers) {
            return $this->providerKeys[0]->getSignatureKeySet($kid);
        }

        $issuer = \is_array($claims) ? ($claims['iss'] ?? null) : null;

        if (!\is_string($issuer)) {
            throw new InvalidSignatureException();
        }

        $issuers = $this->announcedIssuers();

        if (!isset($issuers[$issuer])) {
            throw new InvalidSignatureException();
        }

        return $this->providerKeys[$issuers[$issuer]]->getSignatureKeySet($kid);
    }

    private function loadAndVerifyJws(string $accessToken): array
    {
        // Decode the token
        $jwsVerifier = new JWSVerifier($this->signatureAlgorithm);
        $serializerManager = new JWSSerializerManager([new JwsCompactSerializer()]);
        $jws = $serializerManager->unserialize($accessToken);

        $claims = json_decode($jws->getPayload(), true);

        if ($this->providerKeys) {
            $kid = $jws->getSignature(0)->hasProtectedHeaderParameter('kid') ? $jws->getSignature(0)->getProtectedHeaderParameter('kid') : null;
            $jwkset = $this->resolveDiscoveredKeySet(\is_string($kid) ? $kid : null, $claims);
        } else {
            $jwkset = $this->signatureKeyset;
        }

        // Verify the signature
        if (method_exists($jwsVerifier, 'verify')) { // web-token/jwt-library >= 4.3
            $verified = $jwsVerifier->verify($jws, $jwkset, 0)->isVerified();
        } else {
            $verified = $jwsVerifier->verifyWithKeySet($jws, $jwkset, 0);
        }
        if (!$verified) {
            throw new InvalidSignatureException();
        }

        $headerCheckers = [new Checker\AlgorithmChecker($this->signatureAlgorithm->list())];
        $mandatoryHeaders = [];
        if ($this->enforceAtJwtType) {
            $headerCheckers[] = new Checker\CallableChecker('typ', static fn ($value) => \is_string($value) && \in_array(strtolower($value), self::AT_JWT_TYPES, true));
            $mandatoryHeaders[] = 'typ';
        }

        $headerCheckerManager = new Checker\HeaderCheckerManager($headerCheckers, [
            new JWSTokenSupport(),
        ]);
        // if this check fails, an InvalidHeaderException is thrown
        $headerCheckerManager->check($jws, 0, $mandatoryHeaders);

        return $claims;
    }

    private function verifyClaims(array $claims): array
    {
        // Verify the claims
        $checkers = [
            new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\NotBeforeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\ExpirationTimeChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift),
            new Checker\CallableChecker('aud', fn ($value) => $this->matchesAudience($value)),
            new Checker\IssuerChecker($this->issuers),
        ];
        $claimCheckerManager = new ClaimCheckerManager($checkers);

        // if this check fails, an InvalidClaimException is thrown
        return $claimCheckerManager->check($claims, ['iat', 'exp', 'aud', 'iss']);
    }

    /**
     * Tells whether an "aud" claim names one of the audiences this resource server answers for.
     *
     * RFC 9068 §2.2 leaves "aud" to RFC 7519, where it is a string or a list of strings, so both
     * shapes are read, and a single match is enough: an access token minted for several resource
     * servers is meant for each of them.
     */
    private function matchesAudience(mixed $audience): bool
    {
        $audiences = array_filter(\is_array($audience) ? $audience : [$audience], \is_string(...));

        return (bool) array_intersect($this->audiences, $audiences);
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
