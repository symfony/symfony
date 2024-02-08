<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Oidc;

use Symfony\Component\Security\Core\User\OidcUser;

use function Symfony\Component\String\u;

/**
 * Creates {@see OidcUser} from claims.
 *
 * @internal
 */
trait OidcTrait
{
    private function createUser(array $claims): OidcUser
    {
        if (!\function_exists('Symfony\Component\String\u')) {
            throw new \LogicException('You cannot use the "OidcUserInfoTokenHandler" since the String component is not installed. Try running "composer require symfony/string".');
        }

        foreach ($claims as $claim => $value) {
            unset($claims[$claim]);
            if ('' === $value || null === $value) {
                continue;
            }
            $claims[u($claim)->camel()->toString()] = $value;
        }

        if (isset($claims['updatedAt']) && '' !== $claims['updatedAt']) {
            $claims['updatedAt'] = (new \DateTimeImmutable())->setTimestamp($claims['updatedAt']);
        }

        if (\array_key_exists('emailVerified', $claims) && null !== $claims['emailVerified'] && '' !== $claims['emailVerified']) {
            $claims['emailVerified'] = (bool) $claims['emailVerified'];
        }

        if (\array_key_exists('phoneNumberVerified', $claims) && null !== $claims['phoneNumberVerified'] && '' !== $claims['phoneNumberVerified']) {
            $claims['phoneNumberVerified'] = (bool) $claims['phoneNumberVerified'];
        }

        return new OidcUser(...$claims);
    }
}
