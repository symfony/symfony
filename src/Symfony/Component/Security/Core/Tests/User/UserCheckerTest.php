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
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserChecker;

class UserCheckerTest extends TestCase
{
    public function testCheckPostAuthNotAdvancedUserInterface()
    {
        $checker = new UserChecker();

        $this->assertNull($checker->checkPostAuth($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock()));
    }

    public function testCheckPostAuthPass()
    {
        $checker = new UserChecker();
        $this->assertNull($checker->checkPostAuth(new User('John', 'password')));
    }

    public function testCheckPostAuthCredentialsExpired()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\CredentialsExpiredException');
        $checker = new UserChecker();
        $checker->checkPostAuth(new User('John', 'password', [], true, true, false, true));
    }

    public function testCheckPreAuthAccountLocked()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\LockedException');
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, true, false, false));
    }

    public function testCheckPreAuthDisabled()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\DisabledException');
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], false, true, false, true));
    }

    public function testCheckPreAuthAccountExpired()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AccountExpiredException');
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, false, true, true));
    }
}
