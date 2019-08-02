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

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isCredentialsNonExpired')->willReturn(true);

        $this->assertNull($checker->checkPostAuth($account));
    }

    public function testCheckPostAuthCredentialsExpired()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\CredentialsExpiredException');
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isCredentialsNonExpired')->willReturn(false);

        $checker->checkPostAuth($account);
    }

    public function testCheckPreAuthNotAdvancedUserInterface()
    {
        $checker = new UserChecker();

        $this->assertNull($checker->checkPreAuth($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock()));
    }

    public function testCheckPreAuthPass()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(true);
        $account->expects($this->once())->method('isAccountNonExpired')->willReturn(true);

        $this->assertNull($checker->checkPreAuth($account));
    }

    public function testCheckPreAuthAccountLocked()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\LockedException');
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(false);

        $checker->checkPreAuth($account);
    }

    public function testCheckPreAuthDisabled()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\DisabledException');
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(false);

        $checker->checkPreAuth($account);
    }

    public function testCheckPreAuthAccountExpired()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\AccountExpiredException');
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->willReturn(true);
        $account->expects($this->once())->method('isEnabled')->willReturn(true);
        $account->expects($this->once())->method('isAccountNonExpired')->willReturn(false);

        $checker->checkPreAuth($account);
    }
}
