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
        if (!\function_exists(\Symfony\Component\String\u::class)) {
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

        // a string "false" would cast to true, so only a recognizable boolean is kept,
        // and any other value is dropped rather than turned into a verified flag
        foreach (['emailVerified', 'phoneNumberVerified'] as $flag) {
            if (isset($claims[$flag]) && '' !== $claims[$flag] && null === $claims[$flag] = filter_var($claims[$flag], \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE)) {
                unset($claims[$flag]);
            }
        }

        return new OidcUser(...$claims);
    }
}
