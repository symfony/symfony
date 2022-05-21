<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Configuration;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * todo Migrate this factory to framework bundle configuration.
 *
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class ConfigurationFactory
{
    /**
     * @var array<string, class-string<Signer>>
     */
    final public const SIGN_ALGORITHMS = [
        'HS256' => Sha256::class,
        'HS384' => Sha384::class,
        'HS512' => Sha512::class,
        'ES256' => Signer\Ecdsa\Sha256::class,
        'ES384' => Signer\Ecdsa\Sha384::class,
        'ES512' => Signer\Ecdsa\Sha512::class,
        'RS256' => Signer\Rsa\Sha256::class,
        'RS384' => Signer\Rsa\Sha384::class,
        'RS512' => Signer\Rsa\Sha512::class,
    ];

    public static function createFromBase64Encoded(string $algorithm, string $key): Configuration
    {
        $signerClass = self::SIGN_ALGORITHMS[$algorithm];

        return Configuration::forSymmetricSigner(new $signerClass(), InMemory::base64Encoded($key));
    }

    public static function createFromFile(string $algorithm, string $key): Configuration
    {
        $signerClass = self::SIGN_ALGORITHMS[$algorithm];

        return Configuration::forSymmetricSigner(new $signerClass(), InMemory::file($key));
    }

    public static function createFromPlainText(string $algorithm, string $key): Configuration
    {
        $signerClass = self::SIGN_ALGORITHMS[$algorithm];

        return Configuration::forSymmetricSigner(new $signerClass(), InMemory::plainText($key));
    }

    public static function createFromUri(string $algorithm, string $key, HttpClientInterface $client = null): Configuration
    {
        $signerClass = self::SIGN_ALGORITHMS[$algorithm];

        return Configuration::forSymmetricSigner(new $signerClass(), InMemory::plainText(sprintf(<<<KEY
-----BEGIN PUBLIC KEY-----
%s
-----END PUBLIC KEY-----
KEY
            , $client->request('GET', $key)->toArray()['public_key']
        )));
    }
}
