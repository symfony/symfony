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
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct()
    {
    }

    public function getUserIdentifierFrom(string $accessToken): string
    {
        switch ($accessToken) {
            case 'VALID_ACCESS_TOKEN':
                return 'dunglas';
            default:
                throw new BadCredentialsException('Invalid credentials.');
        }
    }
}
