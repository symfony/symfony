<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\Dpop;

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Http\OAuth2\JwsAlgorithms;

/**
 * Signs the DPoP proofs of RFC 9449, which bind what a client is given to the key it holds.
 *
 * A proof is not a credential the holder of the key hands out, it is a signature over one
 * request: the method and the URL it was made for are in it, so a proof taken off the wire
 * cannot be sent anywhere else, and the provider binds the tokens of that request to the
 * key the proof carries. What the provider then hands back is a token nobody can present
 * without signing for it.
 *
 * The key is the client's and it is asymmetric (Section 4.2): the public half travels in the
 * header of every proof, which is how the provider learns the key without anything being
 * registered for it, and the private half never leaves the application.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449 OAuth 2.0 Demonstrating Proof of Possession (DPoP)
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class DpopProofFactory
{
    /**
     * The "typ" of a proof, which Section 4.2 requires and a provider checks so that a JWT
     * signed for anything else cannot be presented as one.
     */
    public const TYPE = 'dpop+jwt';

    /**
     * The header a provider names a nonce in, Section 8.
     */
    public const NONCE_HEADER = 'dpop-nonce';

    private readonly ClockInterface $clock;
    private readonly Algorithm $algorithm;
    private readonly JWK $publicKey;

    /**
     * @param JWK             $signingKey The private key of the client, whose public half every proof carries
     * @param string          $algorithm  The JWA name of the signature algorithm, which must be one the provider
     *                                    lists in the "dpop_signing_alg_values_supported" of its metadata
     * @param ?ClockInterface $clock      The clock a proof is dated with
     */
    public function __construct(
        private readonly JWK $signingKey,
        string $algorithm = 'ES256',
        ?ClockInterface $clock = null,
    ) {
        if (!class_exists(JWSBuilder::class)) {
            throw new \LogicException('You cannot sign DPoP proofs since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        if (!isset(JwsAlgorithms::ASYMMETRIC[$algorithm])) {
            throw new \InvalidArgumentException(\sprintf('The "%s" algorithm cannot sign a DPoP proof. Use one of "%s".', $algorithm, implode('", "', array_keys(JwsAlgorithms::ASYMMETRIC))));
        }

        if (!$signingKey->has('d')) {
            throw new \InvalidArgumentException('A DPoP proof must be signed with the private key of the client, and the given JWK has no "d" parameter: it is the public key.');
        }

        if (null === $clock && !class_exists(Clock::class)) {
            throw new \LogicException(\sprintf('The "symfony/clock" component is required to build "%s" without a clock. Try running "composer require symfony/clock", or pass any PSR-20 clock to the constructor.', self::class));
        }

        $this->algorithm = new (JwsAlgorithms::ASYMMETRIC[$algorithm])();
        // read here and not at the first signature: the first proof is signed on the callback
        // of a user who has already logged in at the provider, where a key of the wrong type
        // is a 500 and a key on the wrong curve is a signature the provider cannot verify
        JwsAlgorithms::checkKey($signingKey, $this->algorithm);
        $this->clock = $clock ?? new Clock();
        // the public half is derived once: it goes in the header of every proof, and a
        // private parameter leaking into it would hand the key to whoever gets the proof
        $this->publicKey = $signingKey->toPublic();
    }

    /**
     * The RFC 7638 thumbprint of the key, which is what a "cnf.jkt" and a "dpop_jkt" name.
     *
     * It names the key without carrying it, which is why an authorization request can bind
     * the code it is about to receive to the key without the key travelling in a front
     * channel (Section 10.1).
     */
    public function getKeyThumbprint(): string
    {
        return $this->signingKey->thumbprint('sha256');
    }

    /**
     * Signs a proof for the request about to be made.
     *
     * The URL is stripped of its query and its fragment, which Section 4.2 excludes from
     * "htu": what the proof is made for is the endpoint, not the parameters sent to it.
     *
     * @param string  $method      The HTTP method of the request, which becomes "htm"
     * @param string  $url         The URL of the request, which becomes "htu"
     * @param ?string $accessToken The access token the request presents, whose digest becomes
     *                             "ath" so that the proof cannot be moved to another token
     * @param ?string $nonce       The nonce the provider last named, Section 8
     */
    public function createProof(string $method, string $url, #[\SensitiveParameter] ?string $accessToken = null, ?string $nonce = null): string
    {
        $now = $this->clock->now()->getTimestamp();

        $claims = [
            'jti' => bin2hex(random_bytes(16)),
            'htm' => strtoupper($method),
            'htu' => self::stripUrl($url),
            'iat' => $now,
        ];

        if (null !== $accessToken) {
            $claims['ath'] = self::base64UrlEncode(hash('sha256', $accessToken, true));
        }

        if (null !== $nonce) {
            $claims['nonce'] = $nonce;
        }

        $jws = (new JWSBuilder(new AlgorithmManager([$this->algorithm])))
            ->withPayload(json_encode($claims, flags: \JSON_THROW_ON_ERROR))
            ->addSignature($this->signingKey, [
                'typ' => self::TYPE,
                'alg' => $this->algorithm->name(),
                'jwk' => $this->publicKey->all(),
            ])
            ->build();

        return (new CompactSerializer())->serialize($jws, 0);
    }

    private static function stripUrl(string $url): string
    {
        return substr($url, 0, strcspn($url, '?#'));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
