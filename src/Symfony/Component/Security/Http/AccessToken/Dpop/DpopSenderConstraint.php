<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Dpop;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\Dpop\Exception\InvalidDpopProofException;
use Symfony\Component\Security\Http\AccessToken\SenderConstraintInterface;

/**
 * Checks the DPoP proof of a request against the key its access token is bound to (RFC 9449, Section 7.1).
 *
 * The proof is a JWT the client signs for one request, and it is trusted for nothing else: the key it is
 * signed with is the one its own header carries, never one looked up by "kid", "jku" or "x5c", and that key
 * is only worth something once its thumbprint (RFC 7638, Section 3) is the "jkt" the access token was bound
 * to. Everything else the proof says is about this very request: the method, the URL, the moment, and the
 * hash of the token presented with it, so that a proof cannot be moved to another request or to another
 * token. A proof is finally remembered for as long as it stands, so that one taken off the wire cannot be
 * sent a second time (Section 11.1).
 *
 * The URL the "htu" claim is compared with is the one this resource server answers on, as the framework
 * resolves it: a deployment behind a reverse proxy therefore declares its trusted proxies and hosts, or the
 * scheme and host it compares are the ones of the internal request rather than the ones the client called.
 *
 * This resource server issues no nonce (Section 8), so a request is never answered with "use_dpop_nonce":
 * the freshness of a proof is what its "iat" and the replay cache make of it.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449
 */
final class DpopSenderConstraint implements SenderConstraintInterface
{
    /**
     * RFC 9449, Section 4.1: the header a proof travels in, and Section 4.2, the "typ" that tells a proof
     * from every other JWT a client signs, so that one is never taken for another (RFC 8725, Section 3.11).
     */
    private const HEADER = 'DPoP';

    private const TYPE = 'dpop+jwt';

    /**
     * RFC 9449, Section 7.1: the authentication scheme a DPoP-bound access token is presented under, and the
     * one the challenge of this resource server names.
     */
    private const SCHEME = 'DPoP';

    /**
     * RFC 7518, Section 6: what the private half of a key holds, the other primes of a multi-prime RSA key among them, the single member of a symmetric key, and the private half of an AKP key.
     */
    private const PRIVATE_MEMBERS = ['d', 'p', 'q', 'dp', 'dq', 'qi', 'oth', 'k', 'priv'];

    /**
     * @param AlgorithmManager       $algorithms       The signature algorithms a proof may be signed with, all
     *                                                 asymmetric: a shared secret proves possession to whoever
     *                                                 shares it. They are what the challenge announces in "algs"
     * @param CacheItemPoolInterface $proofReplayCache Where the "jti" of a proof is remembered for as long as the
     *                                                 proof stands. Resource servers sharing a deployment share
     *                                                 this pool, or a proof replayed against another instance is
     *                                                 not seen for what it is
     * @param int                    $proofLifetime    How long a proof stands, in seconds, from its "iat"
     * @param int                    $allowedTimeDrift What is allowed of the clock of the client, in seconds,
     *                                                 both ways
     */
    public function __construct(
        private readonly AlgorithmManager $algorithms,
        private readonly CacheItemPoolInterface $proofReplayCache,
        private readonly ClockInterface $clock = new Clock(),
        private readonly int $proofLifetime = 60,
        private readonly int $allowedTimeDrift = 5,
        private readonly ?LoggerInterface $logger = null,
    ) {
        if ($proofLifetime < 1) {
            throw new \InvalidArgumentException(\sprintf('The "$proofLifetime" argument of "%s()" must be a positive number of seconds.', __METHOD__));
        }

        if ($allowedTimeDrift < 0) {
            throw new \InvalidArgumentException(\sprintf('The "$allowedTimeDrift" argument of "%s()" cannot be negative.', __METHOD__));
        }
    }

    public function check(Request $request, #[\SensitiveParameter] string $accessToken, array $claims): void
    {
        if (!class_exists(JWSVerifier::class)) {
            throw new \LogicException('You cannot use DPoP since "web-token/jwt-library" is not installed. Try running "composer require web-token/jwt-library".');
        }

        try {
            $jkt = $this->checkProof($request, $accessToken);
        } catch (AuthenticationException $e) {
            $this->logger?->debug('The DPoP proof of the request was refused.', ['error' => $e->getMessage()]);

            throw $e;
        }

        // RFC 9449, Section 7.1: a resource server protecting a DPoP-bound resource refuses a token bound to
        // nothing, whoever holds it. The claim is read exactly as RFC 7800, Section 3.1 writes it, a "cnf"
        // object holding the thumbprint of the key, because a "cnf" shaped otherwise is not one this token
        // was issued with
        $confirmation = $claims['cnf'] ?? null;
        $bound = \is_array($confirmation) ? $confirmation['jkt'] ?? null : null;
        if (!\is_string($bound) || '' === $bound) {
            throw new BadCredentialsException('The access token is not bound to a key (RFC 9449, Section 7.1).');
        }

        if (!hash_equals($bound, $jkt)) {
            throw new BadCredentialsException('The access token is bound to another key than the one the DPoP proof was signed with (RFC 9449, Section 7.1).');
        }
    }

    public function getChallenge(?AuthenticationException $exception): array
    {
        $parameters = ['algs' => implode(' ', $this->algorithms->list())];

        // RFC 9449, Section 7.1: "invalid_dpop_proof" describes the proof, "invalid_token" the token, and a
        // client that can tell the two apart knows whether to sign another proof or to get another token
        if ($exception instanceof InvalidDpopProofException) {
            $parameters['error'] = 'invalid_dpop_proof';
        }

        return [self::SCHEME, $parameters];
    }

    /**
     * The thumbprint of the key the proof of this request was signed with, everything the proof says of
     * itself having been checked against the request that carried it.
     *
     * @throws InvalidDpopProofException
     */
    private function checkProof(Request $request, #[\SensitiveParameter] string $accessToken): string
    {
        // RFC 9449, Section 4.3: a request carries one proof. Several of them are not a proof of anything,
        // whichever one would be picked
        $proofs = $request->headers->all(self::HEADER);
        if (1 !== \count($proofs) || !\is_string($proof = $proofs[0]) || '' === $proof) {
            throw new InvalidDpopProofException('A request presenting a DPoP-bound access token carries exactly one DPoP proof (RFC 9449, Section 4.3).');
        }

        try {
            $jws = (new JWSSerializerManager([new CompactSerializer()]))->unserialize($proof);
        } catch (\Exception $e) {
            throw new InvalidDpopProofException('The DPoP proof is not a compact JWS (RFC 9449, Section 4.2).', 0, $e);
        }

        $header = $jws->getSignature(0)->getProtectedHeader();
        if (self::TYPE !== ($header['typ'] ?? null)) {
            throw new InvalidDpopProofException(\sprintf('A DPoP proof is a JWT of type "%s" (RFC 9449, Section 4.2).', self::TYPE));
        }

        $algorithm = $header['alg'] ?? null;
        if (!\is_string($algorithm) || !\in_array($algorithm, $this->algorithms->list(), true)) {
            throw new InvalidDpopProofException('The DPoP proof is signed with an algorithm this resource server does not accept (RFC 9449, Section 4.2).');
        }

        $values = $this->publicKeyOf($header);

        // a key that is not one, or one whose members do not make the pair its "kty" announces, is a
        // refusal of the proof and not an error of this resource server: what a request brings is read,
        // never trusted to be well formed
        try {
            $key = new JWK($values);
            $verifier = new JWSVerifier($this->algorithms);
            $keyset = new JWKSet([$key]);
            if (method_exists($verifier, 'verify')) { // web-token/jwt-library >= 4.3
                $verified = $verifier->verify($jws, $keyset, 0)->isVerified();
            } else {
                $verified = $verifier->verifyWithKeySet($jws, $keyset, 0);
            }
            $jkt = $key->thumbprint('sha256');
        } catch (\Exception $e) {
            throw new InvalidDpopProofException('The key the DPoP proof carries cannot be read (RFC 9449, Section 4.2).', 0, $e);
        }

        if (!$verified) {
            throw new InvalidDpopProofException('The DPoP proof is not signed by the key it carries (RFC 9449, Section 4.3).');
        }

        $payload = json_decode($jws->getPayload() ?? '', true);
        if (!\is_array($payload)) {
            throw new InvalidDpopProofException('The payload of the DPoP proof is not a JSON object (RFC 9449, Section 4.2).');
        }

        $this->checkRequest($request, $payload);
        $expiresAt = $this->checkIssuedAt($payload);
        $this->checkAccessTokenHash($payload, $accessToken);

        $this->rememberProof($jkt, $payload, $expiresAt);

        return $jkt;
    }

    /**
     * RFC 9449, Section 4.3: the method and the URL of the request the proof was made for, which is what makes
     * a proof a signature over one request rather than a credential its holder hands out.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidDpopProofException
     */
    private function checkRequest(Request $request, array $payload): void
    {
        if (($payload['htm'] ?? null) !== $request->getMethod()) {
            throw new InvalidDpopProofException('The "htm" of the DPoP proof is not the method of this request (RFC 9449, Section 4.3).');
        }

        $url = $payload['htu'] ?? null;
        if (!\is_string($url) || self::normalizeUrl($url) !== self::normalizeUrl($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo())) {
            throw new InvalidDpopProofException('The "htu" of the DPoP proof is not the URL of this request (RFC 9449, Section 4.3).');
        }
    }

    /**
     * RFC 9449, Section 4.3: when the proof stands, which is the window a request needs and no more.
     *
     * @param array<string, mixed> $payload
     *
     * @return int the moment the proof stops standing, as a timestamp
     *
     * @throws InvalidDpopProofException
     */
    private function checkIssuedAt(array $payload): int
    {
        // RFC 7519, Section 2 makes a NumericDate a number, which a client may well send with a fraction;
        // nothing is read from a string, a date a client spelled out being a date nobody checked
        $issuedAt = $payload['iat'] ?? null;
        if (!\is_int($issuedAt) && !\is_float($issuedAt)) {
            throw new InvalidDpopProofException('The DPoP proof carries no "iat" (RFC 9449, Section 4.2).');
        }

        $now = $this->clock->now()->getTimestamp();
        if ($issuedAt > $now + $this->allowedTimeDrift) {
            throw new InvalidDpopProofException('The DPoP proof was made later than the request that carries it (RFC 9449, Section 4.3).');
        }

        if ($issuedAt + $this->proofLifetime + $this->allowedTimeDrift <= $now) {
            throw new InvalidDpopProofException('The DPoP proof is older than this resource server accepts (RFC 9449, Section 4.3).');
        }

        // cast once the window bounds it: a float out of the integer range has no integer value
        return (int) ceil($issuedAt) + $this->proofLifetime + $this->allowedTimeDrift;
    }

    /**
     * RFC 9449, Section 4.3 and Section 7.1: a proof made for a request that presents an access token names
     * that token, so that a proof cannot be moved from one token to another.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidDpopProofException
     */
    private function checkAccessTokenHash(array $payload, #[\SensitiveParameter] string $accessToken): void
    {
        $hash = $payload['ath'] ?? null;
        if (!\is_string($hash) || '' === $hash) {
            throw new InvalidDpopProofException('A DPoP proof presented with an access token names it in its "ath" (RFC 9449, Section 7.1).');
        }

        if (!hash_equals(self::base64UrlEncode(hash('sha256', $accessToken, true)), $hash)) {
            throw new InvalidDpopProofException('The "ath" of the DPoP proof is not the access token this request presents (RFC 9449, Section 7.1).');
        }
    }

    /**
     * RFC 9449, Section 11.1: a proof is used once. It is remembered under the key that signed it and for as
     * long as it stands, which is the whole window a replay could happen in.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidDpopProofException
     */
    private function rememberProof(string $jkt, array $payload, int $expiresAt): void
    {
        $identifier = $payload['jti'] ?? null;
        if (!\is_string($identifier) || '' === $identifier) {
            throw new InvalidDpopProofException('The DPoP proof carries no "jti" (RFC 9449, Section 4.2).');
        }

        $item = $this->proofReplayCache->getItem('dpop.'.hash('xxh128', $jkt.'.'.$identifier));
        if ($item->isHit()) {
            throw new InvalidDpopProofException('The DPoP proof was presented before (RFC 9449, Section 11.1).');
        }

        $this->proofReplayCache->save($item->set(true)->expiresAt(new \DateTimeImmutable('@'.$expiresAt)));
    }

    /**
     * RFC 9449, Section 4.2: the key the proof is signed with travels in its own header, and it is a public
     * key. A key carrying private material is refused rather than stripped of it: a client that sent its
     * private key sent it, and a resource server quietly using the public half would let that pass unsaid.
     *
     * @param array<string, mixed> $header
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDpopProofException
     */
    private function publicKeyOf(array $header): array
    {
        $key = $header['jwk'] ?? null;
        if (!\is_array($key) || !$key) {
            throw new InvalidDpopProofException('The DPoP proof carries no public key in its header (RFC 9449, Section 4.2).');
        }

        foreach (self::PRIVATE_MEMBERS as $member) {
            if (\array_key_exists($member, $key)) {
                throw new InvalidDpopProofException('The key the DPoP proof carries is not a public key (RFC 9449, Section 4.3).');
            }
        }

        return $key;
    }

    /**
     * RFC 9449, Section 4.3: the query and the fragment are no part of the URL a proof names, an endpoint
     * being one whatever a client appends to it. The scheme and the host are compared as URLs are, case
     * insensitively and without the port the scheme implies.
     */
    private static function normalizeUrl(string $url): string
    {
        $url = strstr($url, '#', true) ?: $url;
        $url = strstr($url, '?', true) ?: $url;

        if (false === $parts = parse_url($url)) {
            return $url;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? null;
        if (('https' === $scheme && 443 === $port) || ('http' === $scheme && 80 === $port)) {
            $port = null;
        }

        return $scheme.'://'.strtolower($parts['host'] ?? '').(null === $port ? '' : ':'.$port).($parts['path'] ?? '');
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
