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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\ChainUserChecker;

class ChainUserCheckerTest extends \PHPUnit_Framework_TestCase
{
    const USER_CHECKER_INTERFACE = 'Symfony\Component\Security\Core\User\UserCheckerInterface';
    const USER_INTERFACE = 'Symfony\Component\Security\Core\User\UserInterface';

    public function testDefaultsWithoutFailures()
    {
        $user = $this->getMock(self::USER_INTERFACE);
        $checkers = array(
            $chained1 = $this->getMock(self::USER_CHECKER_INTERFACE),
            $chained2 = $this->getMock(self::USER_CHECKER_INTERFACE),
        );

        $chained1
            ->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        $chained2
            ->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        $chained1
            ->expects($this->once())
            ->method('checkPostAuth')
            ->with($user);

        $chained2
            ->expects($this->once())
            ->method('checkPostAuth')
            ->with($user);

        $chainUserChecker = new ChainUserChecker($checkers);

        $chainUserChecker->checkPreAuth($user);
        $chainUserChecker->checkPostAuth($user);
    }

    /**
     * @dataProvider methodProvider
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     */
    public function testWithFailures($method)
    {
        $user = $this->getMock(self::USER_INTERFACE);
        $checkers = array(
            $chained1 = $this->getMock(self::USER_CHECKER_INTERFACE),
            $chained2 = $this->getMock(self::USER_CHECKER_INTERFACE),
        );

        $chained1
            ->expects($this->once())
            ->method($method)
            ->with($user)
            ->willThrowException(new AuthenticationException());

        $chained2
            ->expects($this->never())
            ->method($method)
            ->with($user);

        $chainUserChecker = new ChainUserChecker($checkers);

        $chainUserChecker->$method($user);
    }

    /**
     * @dataProvider methodProvider
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     */
    public function testWithFailuresOnLastToEnsureSequence($method)
    {
        $user = $this->getMock(self::USER_INTERFACE);
        $checkers = array(
            $chained1 = $this->getMock(self::USER_CHECKER_INTERFACE),
            $chained2 = $this->getMock(self::USER_CHECKER_INTERFACE),
        );

        $chained1
            ->expects($this->once())
            ->method($method)
            ->with($user);

        $chained2
            ->expects($this->once())
            ->method($method)
            ->with($user)
            ->willThrowException(new AuthenticationException());

        $chainUserChecker = new ChainUserChecker($checkers);

        $chainUserChecker->$method($user);
    }

    public function methodProvider()
    {
        return array(array('checkPreAuth'), array('checkPostAuth'));
    }
}
