<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class InMemoryAccessTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @var array<string, UserBadge>
     */
    private $accessTokens = [];

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!\array_key_exists($accessToken, $this->accessTokens)) {
            throw new BadCredentialsException('Invalid access token or invalid user.');
        }

        return $this->accessTokens[$accessToken];
    }

    public function remove(string $accessToken): self
    {
        unset($this->accessTokens[$accessToken]);

        return $this;
    }

    public function add(string $accessToken, UserBadge $user): self
    {
        $this->accessTokens[$accessToken] = $user;

        return $this;
    }
}
