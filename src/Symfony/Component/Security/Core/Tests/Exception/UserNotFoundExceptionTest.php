<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class UserNotFoundExceptionTest extends TestCase
{
    public function testGetMessageData()
    {
        $exception = new UserNotFoundException('Username could not be found.');
        $this->assertEquals(['{{ username }}' => null, '{{ user_identifier }}' => null], $exception->getMessageData());
        $exception->setUserIdentifier('username');
        $this->assertEquals(['{{ username }}' => 'username', '{{ user_identifier }}' => 'username'], $exception->getMessageData());
    }

    /**
     * @group legacy
     */
    public function testUsernameNotFoundException()
    {
        $exception = new UsernameNotFoundException();
        $this->assertInstanceOf(UserNotFoundException::class, $exception);

        $exception->setUsername('username');
        $this->assertEquals('username', $exception->getUserIdentifier());
    }
}
