<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\OAuth2;

use Jose\Component\Checker;
use Jose\Component\Checker\ClaimCheckerManager;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\JWSTokenSupport;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\OAuth2User;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcJwks;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * The token handler validates the token on the authorization server and the Introspection Endpoint.
 *
 * The endpoint is reached by posting to the HTTP client it is given, which carries where the
 * authorization server lives and how this resource server authenticates there: a scoped client
 * declaring the introspection endpoint as its "base_uri" and its credentials as "auth_basic" is all
 * RFC 7662 §2.1 asks for. Nothing of that transport is described here.
 *
 * What is described here is what the resource server expects of the answer. The response is what it
 * bases its authorization decision on, so the members that qualify the token are confronted with
 * those expectations before a user badge is built from them: an authorization server that reports a
 * token as active while dating it in the past or the future, or while naming another issuer or
 * another audience, has described a token this resource server must not honor.
 *
 * The introspection response may also be asked to be a signed JWT, as RFC 9701 defines it: see
 * {@see enableSignedResponse()}. The endpoint is then requested with the matching "Accept" header,
 * the JWT is verified against the keys of the authorization server, and the members of RFC 7662 are
 * read from the "token_introspection" claim it wraps.
 *
 * Anything the introspection request throws is an answer the resource server could not read, so it
 * turns into the bad credentials the firewall reports as a 401: a server that is unreachable, that
 * refuses the caller, or that answers something other than the JSON object RFC 7662 §2.2 defines
 * says nothing about the token, and never that it is usable.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7662 OAuth 2.0 Token Introspection (RFC 7662)
 * @see https://datatracker.ietf.org/doc/html/rfc9701 JWT Response for OAuth Token Introspection (RFC 9701)
 *
 * @internal
 */
final class Oauth2TokenHandler implements AccessTokenHandlerInterface
{
    /**
     * RFC 9701 §5: the media type of a JWT introspection response, also carried by its "typ" header,
     * where RFC 7515 §4.1.9 lets the "application/" prefix be omitted, so both spellings are read.
     */
    private const JWT_RESPONSE_MEDIA_TYPE = 'application/token-introspection+jwt';
    private const JWT_RESPONSE_TYPES = ['token-introspection+jwt', 'application/token-introspection+jwt'];

    /**
     * RFC 8414 §3: the well-known path of the OAuth 2.0 authorization server metadata.
     */
    private const METADATA_PATH = '/.well-known/oauth-authorization-server';

    private ?CacheInterface $cache = null;
    private string $cacheKeyPrefix = '';
    private int $cacheTtl = 0;

    private ?AlgorithmManager $signatureAlgorithms = null;
    private ?JWKSet $signatureKeyset = null;
    private bool $enforceSignedResponse = true;
    private ?OidcDiscovery $metadata = null;
    private ?CacheInterface $metadataCache = null;
    private ?string $metadataCacheKey = null;
    private ?HttpClientInterface $metadataClient = null;

    /**
     * @var list<string>
     */
    private readonly array $audiences;

    /**
     * @param HttpClientInterface $client           The client the introspection endpoint is reached with, whose
     *                                              base URI is that endpoint and whose options carry the
     *                                              credentials of this resource server
     * @param string|list<string> $audiences        The identifiers of this resource server, one of which the "aud"
     *                                              of the introspection response must name, or an empty list to
     *                                              skip that check
     * @param string|null         $issuer           The identifier of the authorization server, checked against the
     *                                              "iss" of the introspection response, or null to skip that check
     * @param string|null         $claim            The claim holding the user identifier, or null to read "sub" and
     *                                              fall back to "username"
     * @param int                 $allowedTimeDrift The tolerance, in seconds, on the "iat", "nbf" and "exp" the
     *                                              authorization server reports
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ?LoggerInterface $logger = null,
        string|array $audiences = [],
        private readonly ?string $issuer = null,
        private readonly ?string $claim = null,
        private readonly ClockInterface $clock = new Clock(),
        private readonly int $allowedTimeDrift = 0,
    ) {
        $this->audiences = \is_array($audiences) ? array_values($audiences) : [$audiences];

        foreach ($this->audiences as $value) {
            if (!\is_string($value) || '' === $value) {
                throw new \InvalidArgumentException(\sprintf('The "$audiences" argument of "%s()" must be a non-empty string or a list of non-empty strings.', __METHOD__));
            }
        }
    }

    /**
     * Caches the introspection responses of active tokens, for at most $ttl seconds.
     *
     * RFC 7662 §4 leaves the trade-off to the resource server: a longer lifetime spares the
     * authorization server a round trip per request, at the cost of a window during which a revoked
     * token is still accepted here. Two bounds are not left to the caller. No entry outlives the
     * "exp" the authorization server reported, which that section makes a MUST NOT, and the
     * response of an inactive token is not cached at all, so that arbitrary token values cannot be
     * used to fill the pool.
     *
     * An entry is keyed by the digest of the token rather than by the token itself, so that a pool
     * an operator, a backup or a neighbouring application can read holds no usable credential.
     */
    public function enableCache(CacheInterface $cache, string $cacheKeyPrefix, int $ttl = 60): void
    {
        if ($ttl <= 0) {
            throw new \InvalidArgumentException(\sprintf('The introspection cache lifetime must be a positive number of seconds, %d given.', $ttl));
        }

        $this->cache = $cache;
        $this->cacheKeyPrefix = $cacheKeyPrefix;
        $this->cacheTtl = $ttl;
    }

    /**
     * Requests a JWT introspection response and verifies its signature, as defined by RFC 9701.
     *
     * @param bool $enforce Whether a plain JSON response must be refused once the authorization server is expected to sign the introspection response
     */
    public function enableSignedResponse(AlgorithmManager $algorithms, ?JWKSet $keyset, bool $enforce = true): void
    {
        $this->signatureAlgorithms = $algorithms;
        $this->signatureKeyset = $keyset;
        $this->enforceSignedResponse = $enforce;
    }

    /**
     * Reads the keys the introspection response is verified against from the authorization server metadata.
     *
     * RFC 8414 §3 builds the metadata URL by inserting the well-known path between the host and the
     * path of the issuer identifier, where OIDC Discovery 1.0 appends it, so the URL is built here
     * and handed to {@see OidcDiscovery} already absolute. The document is the authority for the
     * "jwks_uri" only: which algorithms this resource server accepts stays its own decision, so that
     * an authorization server cannot widen it by announcing more.
     */
    public function enableSignedResponseDiscovery(CacheInterface $cache, HttpClientInterface $client, string $cacheKey): void
    {
        if (null === $this->issuer) {
            throw new \LogicException('The "issuer" of the authorization server is required to read its metadata, since RFC 8414 §3 derives the metadata URL from it.');
        }

        $this->metadataCache = $cache;
        $this->metadataClient = $client;
        $this->metadataCacheKey = $cacheKey;
        $this->metadata = new OidcDiscovery($client, $cache, self::metadataUrl($this->issuer), $this->issuer, cacheKey: $cacheKey.'.document', checkedEndpoints: ['jwks_uri']);
    }

    /**
     * RFC 8414 §3: the well-known path is inserted between the host component and the path of the
     * issuer identifier, so that one host can serve the metadata of several authorization servers.
     */
    private static function metadataUrl(string $issuer): string
    {
        $parts = parse_url(rtrim($issuer, '/'));

        if (!isset($parts['scheme'], $parts['host'])) {
            throw new \LogicException(\sprintf('The "issuer" of the authorization server must be an absolute URL to read its metadata, "%s" given.', $issuer));
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').self::METADATA_PATH.($parts['path'] ?? '');
    }

    /**
     * Introspects the token and builds a user badge out of the members of the response.
     *
     * RFC 7662 §2.2 guarantees a single member on an inactive token, "active", and makes it a
     * boolean, so it is the first one read and it is compared to true: nothing else states that the
     * token can be used, the string "false" an authorization server may answer with least of all.
     * The identifier claims are only looked up once that member has stated it.
     *
     * The JOSE checkers a signed response goes through report a refused "typ" header or a missing
     * mandatory claim with exceptions of their own, which the catch-all around the response covers
     * as it covers those of the HTTP client.
     */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if ((null !== $this->signatureKeyset || null !== $this->metadata) && (!class_exists(JWSVerifier::class) || !class_exists(Checker\HeaderCheckerManager::class))) {
            throw new \LogicException('You cannot verify signed introspection responses since "web-token/jwt-library" is not installed. Try running "composer require web-token/jwt-library".');
        }

        try {
            ['claims' => $claims, 'signed' => $signed] = $this->introspect($accessToken);

            if (true !== ($claims['active'] ?? false)) {
                throw new BadCredentialsException('The claim "active" was not found on the authorization server response or is set to false.');
            }

            $this->verifyClaims($claims, $signed);
            $identifier = $this->getUserIdentifier($claims);

            return new UserBadge($identifier, fn () => $this->createUser($claims), $claims);
        } catch (\Exception $e) {
            $this->logger?->error('An error occurred on the authorization server.', [
                'error' => $e->getMessage(),
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new BadCredentialsException('Invalid credentials.', $e->getCode(), $e);
        }
    }

    /**
     * Returns the members of the introspection response, from the cache when one is enabled.
     *
     * The lifetime of an entry is the shorter of the configured one and of what the "exp" of the
     * response leaves, and only the response of an active token is stored: see {@see enableCache()}.
     *
     * @return array{claims: array<string, mixed>, signed: bool} The members of the introspection response, and whether they were read from a signed one
     */
    private function introspect(string $accessToken): array
    {
        if (null === $this->cache) {
            return $this->requestIntrospection($accessToken);
        }

        $key = $this->cacheKeyPrefix.hash('sha256', $accessToken);

        return $this->cache->get($key, function (ItemInterface $item, bool &$save) use ($accessToken): array {
            $response = $this->requestIntrospection($accessToken);

            $ttl = $this->cacheTtl;
            if (is_numeric($response['claims']['exp'] ?? null)) {
                $ttl = min($ttl, (int) $response['claims']['exp'] - $this->clock->now()->getTimestamp());
            }

            $save = 0 < $ttl && true === ($response['claims']['active'] ?? false);
            $item->expiresAfter(max(1, $ttl));

            return $response;
        });
    }

    /**
     * Calls the introspection endpoint, and returns the members it answered with.
     *
     * The request carries the token and the "access_token" hint of RFC 7662 §2.1, and is posted to
     * the base URI of the client, which is where the endpoint and the credentials of this resource
     * server are configured. It asks for the media types it is able to read: a JWT introspection
     * response is only ever served to a request announcing it, per RFC 9701 §4, and plain JSON is
     * still offered alongside it when an unsigned response is tolerated, so that an authorization
     * server that cannot sign answers instead of refusing the media type. The response headers are
     * read before its body, so that the payload of a 4xx or a 5xx is never mistaken for an
     * introspection response.
     *
     * @return array{claims: array<string, mixed>, signed: bool}
     */
    private function requestIntrospection(string $accessToken): array
    {
        if (!$this->verifiesSignedResponses()) {
            $accept = 'application/json';
        } else {
            $accept = $this->enforceSignedResponse ? self::JWT_RESPONSE_MEDIA_TYPE : self::JWT_RESPONSE_MEDIA_TYPE.', application/json';
        }

        $response = $this->client->request('POST', '', [
            'headers' => ['Accept' => $accept],
            'body' => [
                'token' => $accessToken,
                'token_type_hint' => 'access_token',
            ],
        ]);
        $contentType = strtolower(trim(strtok($response->getHeaders()['content-type'][0] ?? '', ';')));

        if (self::JWT_RESPONSE_MEDIA_TYPE === $contentType) {
            if (!$this->verifiesSignedResponses()) {
                throw new BadCredentialsException('The authorization server returned a JWT introspection response, which this resource server is not configured to verify.');
            }

            return ['claims' => $this->verifySignedResponse($response->getContent()), 'signed' => true];
        }

        if ($this->verifiesSignedResponses() && $this->enforceSignedResponse) {
            throw new BadCredentialsException(\sprintf('A signed introspection response is required, but the authorization server answered with "%s".', $contentType ?: 'no content type'));
        }

        return ['claims' => $response->toArray(), 'signed' => false];
    }

    private function verifiesSignedResponses(): bool
    {
        return null !== $this->signatureKeyset || null !== $this->metadata;
    }

    /**
     * The keys the introspection response is verified against: the configured set, or the one the
     * authorization server publishes at the "jwks_uri" its metadata announces.
     */
    private function resolveSignatureKeyset(): JWKSet
    {
        if (null !== $this->signatureKeyset) {
            return $this->signatureKeyset;
        }

        return JWKSet::createFromKeyData(['keys' => $this->metadataCache->get($this->metadataCacheKey, $this->computeMetadataKeys(...))]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function computeMetadataKeys(ItemInterface $item): array
    {
        [$keys, $ttl] = OidcJwks::fromResponse($this->metadataClient->request('GET', $this->metadata->getConfiguration()['jwks_uri'], ['max_redirects' => 0]), true);

        if (0 < ($ttl ?? -1)) {
            $item->expiresAfter(min($ttl, OidcJwks::MAX_TTL));
        }

        return $keys;
    }

    /**
     * Verifies a JWT introspection response and returns the members it wraps.
     *
     * Beyond the signature, two things are checked because RFC 9701 §8.1 rests on them to keep an
     * access token or an ID token of the same issuer from being passed off as an introspection
     * response: the "typ" header, and the nesting of the RFC 7662 members inside the
     * "token_introspection" claim. The "iss", "aud" and "iat" claims that §5 makes mandatory at the
     * top level are required as well, and confronted with the issuer and the audiences declared here.
     *
     * The library is supported from 3.x, where verify() does not exist yet and verifyWithKeySet()
     * is not deprecated yet; static analysis only ever sees the newest version, hence the two
     * ignores around the verification.
     *
     * @return array<string, mixed>
     */
    private function verifySignedResponse(string $jwt): array
    {
        try {
            $jws = (new JWSSerializerManager([new CompactSerializer()]))->unserialize($jwt);
        } catch (\InvalidArgumentException $e) {
            throw new BadCredentialsException('The introspection response is not a valid JWT.', previous: $e);
        }

        $keyset = $this->resolveSignatureKeyset();
        $jwsVerifier = new JWSVerifier($this->signatureAlgorithms);

        if (method_exists($jwsVerifier, 'verify')) { // @phpstan-ignore function.alreadyNarrowedType
            $verified = $jwsVerifier->verify($jws, $keyset, 0)->isVerified();
        } else {
            $verified = $jwsVerifier->verifyWithKeySet($jws, $keyset, 0); // @phpstan-ignore method.deprecated
        }

        if (!$verified) {
            throw new BadCredentialsException('The signature of the introspection response is invalid.');
        }

        (new Checker\HeaderCheckerManager([
            new Checker\AlgorithmChecker($this->signatureAlgorithms->list()),
            new Checker\CallableChecker('typ', static fn ($value) => \is_string($value) && \in_array(strtolower($value), self::JWT_RESPONSE_TYPES, true)),
        ], [new JWSTokenSupport()]))->check($jws, 0, ['alg', 'typ']);

        try {
            $payload = json_decode($jws->getPayload() ?? '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadCredentialsException('The payload of the introspection response is not valid JSON.', previous: $e);
        }

        if (!\is_array($payload)) {
            throw new BadCredentialsException('The payload of the introspection response is not a JSON object.');
        }

        $checkers = [new Checker\IssuedAtChecker(clock: $this->clock, allowedTimeDrift: $this->allowedTimeDrift)];
        if (null !== $this->issuer) {
            $checkers[] = new Checker\IssuerChecker([$this->issuer]);
        }
        if ($this->audiences) {
            $checkers[] = new Checker\CallableChecker('aud', fn ($value) => $this->matchesAudience($value));
        }
        (new ClaimCheckerManager($checkers))->check($payload, ['iss', 'aud', 'iat', 'token_introspection']);

        if (!\is_array($payload['token_introspection'])) {
            throw new BadCredentialsException('The "token_introspection" claim of the introspection response is not a JSON object.');
        }

        return $payload['token_introspection'];
    }

    /**
     * Confronts the members of the introspection response with what this resource server expects.
     *
     * RFC 7662 §2.2 makes all of them optional and §4 puts the checks on the authorization server,
     * so the dates are only verified when the response carries them: a resource server told the
     * token is already expired, not valid yet or minted in the future still has no reason to honor
     * it. The issuer and the audience are checked when this resource server declares which ones it
     * expects, and the response is then required to carry them, since a token issued by another
     * authorization server, or for another resource server, must not be honored. A signed response
     * is the exception: RFC 9701 §5 already binds it to both at the top level of the JWT, so the
     * members it wraps are only confronted with them when they repeat them.
     *
     * @param array<string, mixed> $claims
     * @param bool                 $signed Whether the members come from a JWT introspection response, whose issuer and audience were verified at its top level
     */
    private function verifyClaims(array $claims, bool $signed): void
    {
        $now = $this->clock->now()->getTimestamp();

        if (null !== ($exp = $this->readTimestamp($claims, 'exp')) && $exp <= $now - $this->allowedTimeDrift) {
            throw new BadCredentialsException('The token reported by the authorization server has expired.');
        }

        if (null !== ($nbf = $this->readTimestamp($claims, 'nbf')) && $nbf > $now + $this->allowedTimeDrift) {
            throw new BadCredentialsException('The token reported by the authorization server is not valid yet.');
        }

        if (null !== ($iat = $this->readTimestamp($claims, 'iat')) && $iat > $now + $this->allowedTimeDrift) {
            throw new BadCredentialsException('The token reported by the authorization server was issued in the future.');
        }

        if (null !== $this->issuer && (!$signed || isset($claims['iss'])) && $this->issuer !== ($claims['iss'] ?? null)) {
            throw new BadCredentialsException(\sprintf('The token was issued by "%s", where "%s" was expected.', \is_string($claims['iss'] ?? null) ? $claims['iss'] : '', $this->issuer));
        }

        if ($this->audiences && (!$signed || isset($claims['aud'])) && !$this->matchesAudience($claims['aud'] ?? null)) {
            throw new BadCredentialsException(\sprintf('The token is not intended for any of the audiences "%s".', implode('", "', $this->audiences)));
        }
    }

    /**
     * Reads a date member of the introspection response as a timestamp, or null when it is absent.
     *
     * RFC 7662 §2.2 types "exp", "nbf" and "iat" as integer timestamps, so a member carrying anything
     * else describes no instant this resource server can confront its clock with, and the response is
     * refused rather than honored, as {@see \Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler}
     * refuses the same shape. A JSON null is read as an absent member, which §2.2 allows.
     *
     * @param array<string, mixed> $claims
     */
    private function readTimestamp(array $claims, string $claim): ?int
    {
        if (null === $value = $claims[$claim] ?? null) {
            return null;
        }

        if (!is_numeric($value) || !is_finite($seconds = (float) $value) || \PHP_INT_MAX <= abs($seconds)) {
            throw new BadCredentialsException(\sprintf('The "%s" claim reported by the authorization server is not a timestamp.', $claim));
        }

        return (int) $value;
    }

    /**
     * Tells whether an "aud" claim names one of the audiences this resource server answers for.
     *
     * RFC 7662 §2.2 makes "aud" "a service-specific string identifier or list of string identifiers",
     * so both shapes are read, and a single match is enough: an access token minted for several
     * resource servers is meant for each of them.
     */
    private function matchesAudience(mixed $audience): bool
    {
        $audiences = array_filter(\is_array($audience) ? $audience : [$audience], \is_string(...));

        return (bool) array_intersect($this->audiences, $audiences);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function getUserIdentifier(array $claims): string
    {
        if (null !== $this->claim) {
            if (null === $identifier = self::readIdentifier($claims, $this->claim)) {
                throw new BadCredentialsException(\sprintf('"%s" claim not found on the authorization server response.', $this->claim));
            }

            return $identifier;
        }

        $identifier = self::readIdentifier($claims, 'sub') ?? self::readIdentifier($claims, 'username');
        if (null === $identifier) {
            throw new BadCredentialsException('"sub" and "username" claims not found on the authorization server response. At least one is required.');
        }

        return $identifier;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private static function readIdentifier(array $claims, string $claim): ?string
    {
        $value = $claims[$claim] ?? null;

        return (\is_string($value) || \is_int($value)) && '' !== (string) $value ? (string) $value : null;
    }

    private function createUser(array $claims): OAuth2User
    {
        if (!\function_exists(\Symfony\Component\String\u::class)) {
            throw new \LogicException('You cannot use the "OAuth2TokenHandler" since the String component is not installed. Try running "composer require symfony/string".');
        }

        foreach ($claims as $claim => $value) {
            unset($claims[$claim]);
            if ('' === $value || null === $value) {
                continue;
            }
            $claims[u($claim)->camel()->toString()] = $value;
        }

        if ('' !== ($claims['updatedAt'] ?? '')) {
            $claims['updatedAt'] = (new \DateTimeImmutable())->setTimestamp($claims['updatedAt']);
        }

        // a string "false" would cast to true, so only a recognizable boolean is kept,
        // and any other value is dropped rather than turned into a verified flag
        foreach (['emailVerified', 'phoneNumberVerified'] as $flag) {
            if (isset($claims[$flag]) && '' !== $claims[$flag] && null === $claims[$flag] = filter_var($claims[$flag], \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE)) {
                unset($claims[$flag]);
            }
        }

        return new OAuth2User(...$claims);
    }
}
