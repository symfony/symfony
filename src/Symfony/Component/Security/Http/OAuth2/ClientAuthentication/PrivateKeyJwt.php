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
use Jose\Component\Signature\JWSBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Http\OAuth2\JwsAlgorithms;

/**
 * Authenticates the client with an assertion signed by its private key.
 *
 * This is the "private_key_jwt" method of OIDC Core 1.0, Section 9, the one FAPI 2.0 asks
 * for: the provider only ever holds the public half of the key, registered as the client
 * "jwks" or fetched from its "jwks_uri", so a provider that is compromised, or one the
 * client was tricked into talking to, learns nothing it could authenticate as the client with.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#ClientAuthentication
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class PrivateKeyJwt extends AbstractClientAssertion
{
    /**
     * @param JWK             $signingKey The private key of the client, whose public half is registered at the provider
     * @param string          $algorithm  The JWA name of the signature algorithm, which must be one the provider lists
     *                                    in the "token_endpoint_auth_signing_alg_values_supported" of its metadata
     * @param int             $lifetime   How long the assertion is valid, in seconds
     * @param ?ClockInterface $clock      The clock the assertion is dated with
     */
    public function __construct(
        JWK $signingKey,
        string $algorithm = 'RS256',
        int $lifetime = 60,
        ?ClockInterface $clock = null,
    ) {
        if (!class_exists(JWSBuilder::class)) {
            throw new \LogicException('You cannot authenticate an OAuth2 client with the "private_key_jwt" method since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library".');
        }

        $signatureAlgorithm = self::createAlgorithm($algorithm, JwsAlgorithms::ASYMMETRIC, 'private_key_jwt');

        if (!$signingKey->has('d')) {
            throw new \InvalidArgumentException('The "private_key_jwt" client assertion must be signed with the private key of the client, and the given JWK has no "d" parameter: it is the public key. Register that public key at the provider, and sign with the private one.');
        }

        parent::__construct($signingKey, $signatureAlgorithm, $lifetime, $clock);
    }

    public function getMethod(): string
    {
        return 'private_key_jwt';
    }
}
