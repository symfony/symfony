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
use Symfony\Component\Security\Core\User\InMemoryUser;
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
        $this->assertNull($checker->checkPostAuth(new InMemoryUser('John', 'password')));
    }
}
