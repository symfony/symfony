<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Jose\Component\Core\Algorithm as AlgorithmInterface;
use Jose\Component\Signature\Algorithm;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenHandler;

/**
 * Creates a signature algorithm for {@see OidcTokenHandler}.
 *
 * @experimental
 */
final class SignatureAlgorithmFactory
{
    public static function create(string $algorithm): AlgorithmInterface
    {
        switch ($algorithm) {
            case 'ES256':
            case 'ES384':
            case 'ES512':
                if (!class_exists(Algorithm::class.'\\'.$algorithm)) {
                    throw new \LogicException(sprintf('You cannot use the "%s" signature algorithm since "web-token/jwt-signature-algorithm-ecdsa" is not installed. Try running "composer require web-token/jwt-signature-algorithm-ecdsa".', $algorithm));
                }

                $algorithm = Algorithm::class.'\\'.$algorithm;

                return new $algorithm();
        }

        throw new InvalidArgumentException(sprintf('Unsupported signature algorithm "%s". Only ES* algorithms are supported. If you want to use another algorithm, create your TokenHandler as a service.', $algorithm));
    }
}
