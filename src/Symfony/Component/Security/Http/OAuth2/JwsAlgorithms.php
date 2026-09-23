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
     * The MAC algorithms, keyed with a secret both the client and the provider hold.
     *
     * @var array<string, class-string<MacAlgorithm>>
     */
    public const MAC = [
        'HS256' => HS256::class,
        'HS384' => HS384::class,
        'HS512' => HS512::class,
    ];
}
