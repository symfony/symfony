<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\ClientAuthentication;

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;

/**
 * Authenticates the client with a JWT it signs itself, the assertion of RFC 7523, Section 2.2.
 *
 * Nothing secret is then sent to the provider: the request carries a short-lived assertion
 * naming the client as both its issuer and its subject, and the token endpoint as its
 * audience, so that an assertion captured at one provider cannot be replayed at another.
 * What signs it is what tells the two methods built on this apart, {@see PrivateKeyJwt}
 * holding a key the provider only knows the public half of, {@see ClientSecretJwt} the
 * shared secret itself.
 *
 * The assertion is single use: it carries a "jti" and a lifetime of a minute by default,
 * which is what RFC 7523, Section 3, item 7 lets the provider reject a replay against.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7523#section-2.2                    JWT client authentication
 * @see https://openid.net/specs/openid-connect-core-1_0.html#ClientAuthentication   OIDC Core 1.0, Section 9
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
abstract class AbstractClientAssertion implements ClientAuthenticationInterface
{
    /**
     * The assertion format RFC 7523, Section 2.2 registers, and the only value the
     * "client_assertion_type" parameter takes when the assertion is a JWT.
     */
    public const ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

    private readonly ClockInterface $clock;

    /**
     * @param JWK       $signingKey The key the assertion is signed with
     * @param Algorithm $algorithm  The algorithm it is signed with, which the subclass picked from
     *                              the ones its method allows, see {@see createAlgorithm()}
     * @param int       $lifetime   How long the assertion is valid, in seconds; it is built for one
     *                              request and sent right away, so it is short by design
     */
    protected function __construct(
        private readonly JWK $signingKey,
        private readonly Algorithm $algorithm,
        private readonly int $lifetime,
        ?ClockInterface $clock,
    ) {
        if (0 >= $lifetime) {
            throw new \InvalidArgumentException(\sprintf('The lifetime of an OAuth2 client assertion must be a positive number of seconds, got %d.', $lifetime));
        }

        if (null === $clock && !class_exists(Clock::class)) {
            throw new \LogicException(\sprintf('The "symfony/clock" component is required to build "%s" without a clock. Try running "composer require symfony/clock", or pass any PSR-20 clock to the constructor.', static::class));
        }

        $this->clock = $clock ?? new Clock();
    }

    /**
     * RFC 7523, Section 2.2: the assertion is added to the token request body next to the
     * "client_assertion_type" naming its format, and nothing else the request already
     * carries is touched, the "client_id" of RFC 7521, Section 4.2 among it.
     */
    final public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        $options['body']['client_assertion_type'] = self::ASSERTION_TYPE;
        $options['body']['client_assertion'] = $this->createAssertion($clientId, $tokenEndpoint);

        return $options;
    }

    /**
     * Resolves an algorithm name to the implementation of it, refusing the ones the method
     * does not allow.
     *
     * The algorithm decides what the key may be, so this allowlist is what keeps a shared
     * secret out of "private_key_jwt" and an asymmetric key out of "client_secret_jwt":
     * either confusion turns a value one party holds into a signature the other party is
     * supposed to be the only one able to make.
     *
     * @template T of Algorithm
     *
     * @param array<string, class-string<T>> $allowedAlgorithms The algorithms the method allows, indexed by their JWA name
     * @param string                         $method            The RFC 7591, Section 2 name of the method, quoted in the error messages
     *
     * @return T
     */
    protected static function createAlgorithm(string $algorithm, array $allowedAlgorithms, string $method): Algorithm
    {
        if (!isset($allowedAlgorithms[$algorithm])) {
            throw new \InvalidArgumentException(\sprintf('The "%s" algorithm cannot sign a "%s" client assertion. Use one of "%s".', $algorithm, $method, implode('", "', array_keys($allowedAlgorithms))));
        }

        return new $allowedAlgorithms[$algorithm]();
    }

    /**
     * Builds the assertion of RFC 7523, Section 3.
     *
     * The client is both the issuer and the subject, as Section 3, items 1 and 2 require
     * from a client authenticating itself, and the audience is the token endpoint the
     * request is about to be made to, which OIDC Core 1.0, Section 9 recommends over the
     * other identifier of the provider Section 3, item 3 allows.
     *
     * The "kid" header is set whenever the key carries one, so that a provider holding
     * several public keys for the client knows which one verifies the signature without
     * trying them all, as OIDC Core 1.0, Section 10.1 asks of a rotating client.
     */
    private function createAssertion(string $clientId, string $tokenEndpoint): string
    {
        $now = $this->clock->now()->getTimestamp();

        $claims = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $tokenEndpoint,
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + $this->lifetime,
        ];

        $header = ['alg' => $this->algorithm->name()];
        if ($this->signingKey->has('kid') && \is_string($kid = $this->signingKey->get('kid'))) {
            $header['kid'] = $kid;
        }

        $jws = (new JWSBuilder(new AlgorithmManager([$this->algorithm])))
            ->withPayload(json_encode($claims, flags: \JSON_THROW_ON_ERROR))
            ->addSignature($this->signingKey, $header)
            ->build();

        return (new CompactSerializer())->serialize($jws, 0);
    }
}
