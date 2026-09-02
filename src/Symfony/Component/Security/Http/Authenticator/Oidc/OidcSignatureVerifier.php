<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Jose\Component\Checker;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Verifies the signature of an OIDC ID token against the provider's JWKS.
 *
 * The signing keys are discovered from the "jwks_uri" of the discovery document
 * and cached, as the "oidc" access token handler does. Verifying the signature is
 * what OIDC Core 1.0, Section 3.1.3.7, item 6 allows to replace by the transport
 * security of the token endpoint request: doing it anyway keeps the guarantee
 * independent of the TLS configuration of the HTTP client in use.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcSignatureVerifier
{
    /**
     * The asymmetric algorithms the "oidc" access token handler supports too. No HMAC
     * algorithm is part of them, so a public key published by the provider can never be
     * turned into the shared secret of an "HS256" token (key confusion).
     */
    private const SIGNATURE_ALGORITHMS = [
        'RS256' => RS256::class,
        'RS384' => RS384::class,
        'RS512' => RS512::class,
        'ES256' => ES256::class,
        'ES384' => ES384::class,
        'ES512' => ES512::class,
        'PS256' => PS256::class,
        'PS384' => PS384::class,
        'PS512' => PS512::class,
    ];

    /**
     * How long a JWKS refetched for an unknown "kid" is kept before another one is allowed.
     */
    private const ROTATION_COOLDOWN = 60;

    /**
     * @param list<string> $algorithms                  The signature algorithms accepted to verify the ID token (e.g. ["RS256"])
     * @param int          $jwksCacheTtl                The JWKS cache lifetime used when the provider advertises none
     * @param bool         $enforceKeyUsageVerification Whether to only accept keys explicitly designated for signature,
     *                                                  see {@see OidcJwks::fromResponse()}
     */
    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly CacheInterface $jwksCache,
        private readonly HttpClientInterface $httpClient,
        private readonly array $algorithms = ['RS256'],
        private readonly int $jwksCacheTtl = 3600,
        private readonly bool $enforceKeyUsageVerification = true,
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    /**
     * Verifies the signature of the given ID token and returns its claims.
     *
     * The claims are only structurally valid at this point: they still have to be
     * validated by {@see OidcIdToken::validateClaims()}.
     *
     * @return array<string, mixed> The claims of the verified ID token
     *
     * @throws AuthenticationException If the signature cannot be verified
     */
    public function verify(string $idToken): array
    {
        if (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class)) {
            throw new \LogicException('You cannot verify OIDC ID token signatures since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        $algorithms = $this->createAlgorithmManager();

        try {
            $jws = (new JWSSerializerManager([new CompactSerializer()]))->unserialize($idToken);
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException('Invalid ID token format.', previous: $e);
        }

        // The "alg" header is checked first, and required: JWSVerifier throws on an
        // algorithm it does not know, "none" among them, instead of reporting a failed
        // verification. Checking it here also rejects a token announcing an algorithm
        // the provider is not configured for before any request is sent to its JWKS.
        try {
            (new Checker\HeaderCheckerManager([new Checker\AlgorithmChecker($algorithms->list())], [new JWSTokenSupport()]))->check($jws, 0, ['alg']);
        } catch (Checker\InvalidHeaderException|Checker\MissingMandatoryHeaderParameterException $e) {
            throw new AuthenticationException(\sprintf('The ID token is not signed with any of the expected algorithms ("%s").', implode('", "', $algorithms->list())), previous: $e);
        }

        $keys = $this->getKeys($jws->getSignature(0)->hasProtectedHeaderParameter('kid') ? $jws->getSignature(0)->getProtectedHeaderParameter('kid') : null);
        if (!$keys) {
            throw new AuthenticationException('The OIDC provider published no signing key usable to verify the ID token signature.');
        }

        $jwsVerifier = new JWSVerifier($algorithms);
        $jwkSet = JWKSet::createFromKeyData(['keys' => $keys]);

        try {
            // the component supports web-token/jwt-library 3.x too, where verify() does not
            // exist and verifyWithKeySet() is not deprecated yet; static analysis only ever
            // sees the newest version, hence the two ignores
            if (method_exists($jwsVerifier, 'verify')) { // @phpstan-ignore function.alreadyNarrowedType
                $verified = $jwsVerifier->verify($jws, $jwkSet, 0)->isVerified();
            } else {
                $verified = $jwsVerifier->verifyWithKeySet($jws, $jwkSet, 0); // @phpstan-ignore method.deprecated
            }
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException('The ID token signature could not be verified.', previous: $e);
        }

        if (!$verified) {
            throw new AuthenticationException('The ID token signature is invalid.');
        }

        try {
            $claims = json_decode($jws->getPayload() ?? '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        if (!\is_array($claims)) {
            throw new AuthenticationException('Invalid ID token payload.');
        }

        return $claims;
    }

    private function createAlgorithmManager(): AlgorithmManager
    {
        return new AlgorithmManager(array_map(static function (string $name): Algorithm {
            if (!isset(self::SIGNATURE_ALGORITHMS[$name])) {
                throw new \LogicException(\sprintf('Unsupported OIDC ID token signature algorithm "%s". Supported algorithms are: "%s".', $name, implode('", "', array_keys(self::SIGNATURE_ALGORITHMS))));
            }

            return new (self::SIGNATURE_ALGORITHMS[$name])();
        }, $this->algorithms));
    }

    /**
     * Returns the cached signing keys of the provider, refetching them when the ID token
     * announces a "kid" none of them holds.
     *
     * A provider that rotated its keys signs with one the cached JWKS does not know yet.
     * The refetch is throttled, so that tokens carrying an unknown "kid" cannot drive
     * one outbound request each.
     *
     * @return list<array<string, mixed>>
     */
    private function getKeys(?string $kid): array
    {
        $jwksUri = $this->discovery->getConfiguration()['jwks_uri'] ?? null;
        if (!\is_string($jwksUri) || '' === $jwksUri) {
            throw new AuthenticationException('The OIDC provider announces no "jwks_uri", which is required to verify the ID token signature.');
        }

        // the JWKS carries the very keys the verification relies on, so a discovery
        // document downgrading its transport to plain HTTP must not be honored
        $jwksUri = $this->discovery->getSecureEndpoint('jwks_uri');

        $cacheKey = 'oidc_jwks.'.hash('xxh128', $jwksUri);
        $compute = fn (ItemInterface $item): array => [
            'keys' => OidcJwks::fetchKeys($this->httpClient, $jwksUri, $item, $this->jwksCacheTtl, $this->enforceKeyUsageVerification),
            // the JWKS is stored with the time it was fetched, so that the rotation
            // refetch below can be throttled without a second cache entry
            'fetched_at' => $this->clock->now()->getTimestamp(),
        ];

        /** @var array{keys: list<array<string, mixed>>, fetched_at: int} $jwks */
        $jwks = $this->jwksCache->get($cacheKey, $compute);

        if (null !== $kid
            && !$this->hasKey($jwks['keys'], $kid)
            && ($jwks['fetched_at'] ?? 0) <= $this->clock->now()->getTimestamp() - self::ROTATION_COOLDOWN
        ) {
            $jwks = $this->jwksCache->get($cacheKey, $compute, \INF);
        }

        return $jwks['keys'];
    }

    /**
     * @param list<array<string, mixed>> $keys
     */
    private function hasKey(array $keys, string $kid): bool
    {
        foreach ($keys as $key) {
            if (isset($key['kid']) && hash_equals($kid, (string) $key['kid'])) {
                return true;
            }
        }

        return false;
    }
}
