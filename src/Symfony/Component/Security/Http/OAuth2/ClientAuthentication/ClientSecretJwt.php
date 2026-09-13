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

use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\Algorithm\MacAlgorithm;
use Jose\Component\Signature\JWSBuilder;
use Psr\Clock\ClockInterface;

/**
 * Authenticates the client with an assertion signed with its secret.
 *
 * This is the "client_secret_jwt" method of OIDC Core 1.0, Section 9, which the same
 * section defines as an HMAC keyed with "the octets of the UTF-8 representation of the
 * client_secret". The secret itself never leaves the client, unlike in "client_secret_post"
 * and "client_secret_basic", but the provider still holds it and could sign an assertion in
 * the name of the client: prefer {@see PrivateKeyJwt}, which nobody but the client can sign.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#ClientAuthentication
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class ClientSecretJwt extends AbstractClientAssertion
{
    /**
     * @var array<string, class-string<MacAlgorithm>>
     */
    private const MAC_ALGORITHMS = [
        'HS256' => HS256::class,
        'HS384' => HS384::class,
        'HS512' => HS512::class,
    ];

    /**
     * The secret is not measured here: how long a key an algorithm needs is the business of
     * that algorithm, which RFC 7518, Section 3.2 gives at least as many bytes as the digest
     * it produces. The algorithm is exercised once on the key instead, so that a secret it
     * refuses fails on the service rather than on the first token request made with it.
     *
     * @param string          $clientSecret The secret shared with the provider, used as the HMAC key
     * @param string          $algorithm    The JWA name of the MAC algorithm, which must be one the provider lists
     *                                      in the "token_endpoint_auth_signing_alg_values_supported" of its metadata
     * @param int             $lifetime     How long the assertion is valid, in seconds
     * @param ?ClockInterface $clock        The clock the assertion is dated with
     */
    public function __construct(
        #[\SensitiveParameter] string $clientSecret,
        string $algorithm = 'HS256',
        int $lifetime = 60,
        ?ClockInterface $clock = null,
    ) {
        if (!class_exists(JWSBuilder::class)) {
            throw new \LogicException('You cannot authenticate an OAuth2 client with the "client_secret_jwt" method since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        $macAlgorithm = self::createAlgorithm($algorithm, self::MAC_ALGORITHMS, 'client_secret_jwt');
        $signingKey = new JWK(['kty' => 'oct', 'k' => rtrim(strtr(base64_encode($clientSecret), '+/', '-_'), '=')]);

        try {
            $macAlgorithm->hash($signingKey, '');
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf('The OAuth2 client secret cannot key a "client_secret_jwt" assertion signed with "%s", which rejected it: "%s" Ask the provider for a longer secret, or authenticate the client with "PrivateKeyJwt".', $algorithm, $e->getMessage()), previous: $e);
        }

        parent::__construct($signingKey, $macAlgorithm, $lifetime, $clock);
    }

    public function getMethod(): string
    {
        return 'client_secret_jwt';
    }
}
