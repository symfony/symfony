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

class InMemoryAccessTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @var array<string, string>
     */
    private $accessTokens = [];

    public function getUserIdentifierFrom(string $accessToken): string
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

    public function add(string $accessToken, string $user): self
    {
        $this->accessTokens[$accessToken] = $user;

        return $this;
    }
}
