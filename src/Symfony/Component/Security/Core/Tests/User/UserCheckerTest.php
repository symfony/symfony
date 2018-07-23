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

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPostAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPostAuthPassAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isCredentialsNonExpired')->will($this->returnValue(true));

        $this->assertNull($checker->checkPostAuth($account));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\CredentialsExpiredException
     */
    public function testCheckPostAuthCredentialsExpired()
    {
        $checker = new UserChecker();
        $checker->checkPostAuth(new User('John', 'password', array(), true, true, false, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPostAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     * @expectedException \Symfony\Component\Security\Core\Exception\CredentialsExpiredException
     */
    public function testCheckPostAuthCredentialsExpiredAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isCredentialsNonExpired')->will($this->returnValue(false));

        $checker->checkPostAuth($account);
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     */
    public function testCheckPreAuthPassAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(true));
        $account->expects($this->once())->method('isAccountNonExpired')->will($this->returnValue(true));

        $this->assertNull($checker->checkPreAuth($account));
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\LockedException
     */
    public function testCheckPreAuthAccountLocked()
    {
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', array(), true, true, false, false));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     * @expectedException \Symfony\Component\Security\Core\Exception\LockedException
     */
    public function testCheckPreAuthAccountLockedAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(false));

        $checker->checkPreAuth($account);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\DisabledException
     */
    public function testCheckPreAuthDisabled()
    {
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', array(), false, true, false, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     * @expectedException \Symfony\Component\Security\Core\Exception\DisabledException
     */
    public function testCheckPreAuthDisabledAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(false));

        $checker->checkPreAuth($account);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccountExpiredException
     */
    public function testCheckPreAuthAccountExpired()
    {
        $checker = new UserChecker();
        $checker->checkPreAuth(new User('John', 'password', array(), true, false, true, true));
    }

    /**
     * @group legacy
     * @expectedDeprecation Calling "Symfony\Component\Security\Core\User\UserChecker::checkPreAuth()" with an AdvancedUserInterface is deprecated since Symfony 4.1. Create a custom user checker if you wish to keep this functionality.
     * @expectedException \Symfony\Component\Security\Core\Exception\AccountExpiredException
     */
    public function testCheckPreAuthAccountExpiredAdvancedUser()
    {
        $checker = new UserChecker();

        $account = $this->getMockBuilder('Symfony\Component\Security\Core\User\AdvancedUserInterface')->getMock();
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(true));
        $account->expects($this->once())->method('isAccountNonExpired')->will($this->returnValue(false));

        $checker->checkPreAuth($account);
    }
}
