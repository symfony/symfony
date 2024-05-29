<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AccessTokenBundle\Security\Handler;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        return match ($accessToken) {
            'VALID_ACCESS_TOKEN' => new UserBadge('dunglas'),
            'SELF_CONTAINED_ACCESS_TOKEN' => new UserBadge('dunglas', fn () => new InMemoryUser('dunglas', null, ['ROLE_USER'])),
            default => throw new BadCredentialsException('Invalid credentials.'),
        };
    }
}
