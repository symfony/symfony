<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2;

use Jose\Component\Core\Algorithm;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\ES512;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\HS384;
use Jose\Component\Signature\Algorithm\HS512;
use Jose\Component\Signature\Algorithm\MacAlgorithm;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\PS384;
use Jose\Component\Signature\Algorithm\PS512;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;

/**
 * The JWS algorithms an OAuth2 client signs what it sends with, by their JWA name.
 *
 * The two sets are kept apart because what a key may be follows from the algorithm: a
 * shared secret signing where an asymmetric key is expected, or the other way round,
 * turns a value both parties hold into a signature one of them is supposed to be the
 * only one able to make.
 *
 * @internal
 */
final class JwsAlgorithms
{
    /**
     * The asymmetric algorithms, the ones the OIDC signature verifier accepts on the way in.
     *
     * A DPoP proof is signed with one of them, RFC 9449, Section 4.2 excluding symmetric
     * algorithms, and so is a "private_key_jwt" client assertion.
     *
     * @var array<string, class-string<SignatureAlgorithm>>
     */
    public const ASYMMETRIC = [
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
     * The curve each ECDSA algorithm signs with, RFC 7518, Section 3.4.
     *
     * The algorithm names both the hash and the curve, and a signature made over another
     * curve is of the wrong length for it: "web-token/jwt-library" only checks that an EC
     * key carries a "crv", so this is what tells P-384 from P-256 before anything is signed.
     *
     * @var array<string, string>
     */
    public const CURVES = [
        'ES256' => 'P-256',
        'ES384' => 'P-384',
        'ES512' => 'P-521',
    ];

    /**
     * The MAC algorithms, keyed with a secret both the client and the provider hold.
     *
     * @var array<string, class-string<MacAlgorithm>>
     */
    public const MAC = [
        'HS256' => HS256::class,
        'HS384' => HS384::class,
        'HS512' => HS512::class,
    ];

    /**
     * Checks that a key can make the signatures an algorithm is named for.
     *
     * Read before anything is signed rather than at the first signature: a key of the wrong
     * type or of the wrong curve is a misconfiguration, and finding it out on the request
     * that needed it turns it into a 500 on a user who has already logged in elsewhere.
     *
     * @throws \InvalidArgumentException When the key cannot be used with that algorithm
     */
    public static function checkKey(JWK $key, Algorithm $algorithm): void
    {
        $name = $algorithm->name();
        $keyType = $key->has('kty') ? $key->get('kty') : null;

        if (!\in_array($keyType, $algorithm->allowedKeyTypes(), true)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" algorithm signs with a key of the "%s" type, and the given JWK is of the "%s" type.', $name, implode('" or "', $algorithm->allowedKeyTypes()), \is_string($keyType) ? $keyType : get_debug_type($keyType)));
        }

        if (!isset(self::CURVES[$name])) {
            return;
        }

        $curve = $key->has('crv') ? $key->get('crv') : null;

        if (self::CURVES[$name] !== $curve) {
            throw new \InvalidArgumentException(\sprintf('The "%s" algorithm signs with a key on the "%s" curve (RFC 7518, Section 3.4), and the given JWK is on the "%s" curve.', $name, self::CURVES[$name], \is_string($curve) ? $curve : get_debug_type($curve)));
        }
    }
}
