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
use Symfony\Component\Security\Core\User\ChainUserChecker;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ChainUserCheckerTest extends TestCase
{
    public function testForwardsPreAuthToAllUserCheckers()
    {
        $user = $this->createMock(UserInterface::class);

        $checker1 = $this->createMock(UserCheckerInterface::class);
        $checker1->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        $checker2 = $this->createMock(UserCheckerInterface::class);
        $checker2->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        $checker3 = $this->createMock(UserCheckerInterface::class);
        $checker3->expects($this->once())
            ->method('checkPreAuth')
            ->with($user);

        (new ChainUserChecker([$checker1, $checker2, $checker3]))->checkPreAuth($user);
    }

    public function testForwardsPostAuthToAllUserCheckers()
    {
        $user = $this->createMock(UserInterface::class);

        $checker1 = $this->createMock(UserCheckerInterface::class);
        $checker1->expects($this->once())
            ->method('checkPostAuth')
            ->with($user);

        $checker2 = $this->createMock(UserCheckerInterface::class);
        $checker2->expects($this->once())
            ->method('checkPostAuth')
            ->with($user);

        $checker3 = $this->createMock(UserCheckerInterface::class);
        $checker3->expects($this->once())
            ->method('checkPostAuth')
            ->with($user);

        (new ChainUserChecker([$checker1, $checker2, $checker3]))->checkPostAuth($user);
    }
}
