<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CredentialsExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class UserCheckerTest extends TestCase
{
    public function testCheckPostAuthNotAdvancedUserInterface()
    {
        $checker = new UserChecker();

        self::assertNull($checker->checkPostAuth(self::createMock(UserInterface::class)));
    }

    public function testCheckPostAuthPass()
    {
        $checker = new UserChecker();
        self::assertNull($checker->checkPostAuth(new User('John', 'password')));
    }

    public function testCheckPostAuthCredentialsExpired()
    {
        self::expectException(CredentialsExpiredException::class);
        $checker = new UserChecker();
        $checker->checkPostAuth(new User('John', 'password', [], true, true, false, true));
    }

    public function testCheckPreAuthAccountLocked()
    {
        self::expectException(LockedException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, true, false, false));
    }

    public function testCheckPreAuthDisabled()
    {
        self::expectException(DisabledException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], false, true, false, true));
    }

    public function testCheckPreAuthAccountExpired()
    {
        self::expectException(AccountExpiredException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, false, true, true));
    }
}
