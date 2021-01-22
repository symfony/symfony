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
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    public function testCheckPostAuthNotAdvancedUserInterface()
    {
        $checker = new UserChecker();

        $this->assertNull($checker->checkPostAuth($this->createMock(UserInterface::class)));
    }

    public function testCheckPostAuthPass()
    {
        $checker = new UserChecker();
        $this->assertNull($checker->checkPostAuth(new User('John', 'password')));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPostAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPostAuthPassAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isCredentialsNonExpired')->willReturn(true);

        $this->assertNull($checker->checkPostAuth($account));
    }

    public function testCheckPostAuthCredentialsExpired()
    {
        $this->expectException(CredentialsExpiredException::class);
        $checker = new UserChecker();
        $checker->checkPostAuth(new User('John', 'password', [], true, true, false, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPostAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPostAuthCredentialsExpiredAdvancedUser()
    {
        $this->expectException(CredentialsExpiredException::class);
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isCredentialsNonExpired')->willReturn(false);

        $checker->checkPostAuth($account);
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPreAuthPassAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(true);
        $account->expects($this->once())->method('isAccountNonExpired')->willReturn(true);

        $this->assertNull($checker->checkPreAuth($account));
    }

    public function testCheckPreAuthAccountLocked()
    {
        $this->expectException(LockedException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, true, false, false));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPreAuthAccountLockedAdvancedUser()
    {
        $this->expectException(LockedException::class);
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(false);

        $checker->checkPreAuth($account);
    }

    public function testCheckPreAuthDisabled()
    {
        $this->expectException(DisabledException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], false, true, false, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPreAuthDisabledAdvancedUser()
    {
        $this->expectException(DisabledException::class);
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(false);

        $checker->checkPreAuth($account);
    }

    public function testCheckPreAuthAccountExpired()
    {
        $this->expectException(AccountExpiredException::class);
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', [], true, false, true, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPreAuthAccountExpiredAdvancedUser()
    {
        $this->expectException(AccountExpiredException::class);
        $checker = new UserChecker();

        $account = $this->createMock(AdvancedUserInterface::class);
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(true);
        $account->expects($this->once())->method('isAccountNonExpired')->willReturn(false);

        $checker->checkPreAuth($account);
    }
}
