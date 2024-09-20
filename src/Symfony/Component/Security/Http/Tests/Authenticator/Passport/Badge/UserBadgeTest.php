<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Passport\Badge;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class UserBadgeTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testUserNotFound()
    {
        $badge = new UserBadge('dummy', fn () => null);
        $this->expectException(UserNotFoundException::class);
        $badge->getUser();
    }

    /**
     * @group legacy
     */
    public function testEmptyUserIdentifier()
    {
        $this->expectUserDeprecationMessage('Since symfony/security-http 7.2: Using an empty string as user identifier is deprecated and will throw an exception in Symfony 8.0.');
        // $this->expectException(BadCredentialsException::class)
        new UserBadge('', fn () => null);
    }
}
