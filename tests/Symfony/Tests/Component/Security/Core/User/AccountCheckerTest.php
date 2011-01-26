<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Core\User;

use Symfony\Component\Security\Core\User\AccountChecker;

class AccountCheckerTest extends \PHPUnit_Framework_TestCase
{
    public function testCheckPreAuthNotAdvancedAccountInterface()
    {
        $checker = new AccountChecker();

        $this->assertNull($checker->checkPreAuth($this->getMock('Symfony\Component\Security\Core\User\AccountInterface')));
    }

    public function testCheckPreAuthPass()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isCredentialsNonExpired')->will($this->returnValue(true));

        $this->assertNull($checker->checkPreAuth($account));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\CredentialsExpiredException
     */
    public function testCheckPreAuthCredentialsExpired()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isCredentialsNonExpired')->will($this->returnValue(false));

        $checker->checkPreAuth($account);
    }

    public function testCheckPostAuthNotAdvancedAccountInterface()
    {
        $checker = new AccountChecker();

        $this->assertNull($checker->checkPostAuth($this->getMock('Symfony\Component\Security\Core\User\AccountInterface')));
    }

    public function testCheckPostAuthPass()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(true));
        $account->expects($this->once())->method('isAccountNonExpired')->will($this->returnValue(true));

        $this->assertNull($checker->checkPostAuth($account));
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\LockedException
     */
    public function testCheckPostAuthAccountLocked()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(false));

        $checker->checkPostAuth($account);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\DisabledException
     */
    public function testCheckPostAuthDisabled()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(false));

        $checker->checkPostAuth($account);
    }

    /**
     * @expectedException Symfony\Component\Security\Core\Exception\AccountExpiredException
     */
    public function testCheckPostAuthAccountExpired()
    {
        $checker = new AccountChecker();

        $account = $this->getMock('Symfony\Component\Security\Core\User\AdvancedAccountInterface');
        $account->expects($this->once())->method('isAccountNonLocked')->will($this->returnValue(true));
        $account->expects($this->once())->method('isEnabled')->will($this->returnValue(true));
        $account->expects($this->once())->method('isAccountNonExpired')->will($this->returnValue(false));

        $checker->checkPostAuth($account);
    }
}
