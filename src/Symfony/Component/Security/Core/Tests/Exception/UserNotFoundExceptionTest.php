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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class UserNotFoundExceptionTest extends TestCase
{
    public function testGetMessageData()
    {
        $exception = new UserNotFoundException('Username could not be found.');
        self::assertEquals(['{{ username }}' => null, '{{ user_identifier }}' => null], $exception->getMessageData());
        $exception->setUserIdentifier('username');
        self::assertEquals(['{{ username }}' => 'username', '{{ user_identifier }}' => 'username'], $exception->getMessageData());
    }

    public function testUserIdentifierIsNotSetByDefault()
    {
        $exception = new UserNotFoundException();

        self::assertNull($exception->getUserIdentifier());
    }

    /**
     * @group legacy
     */
    public function testUsernameIsNotSetByDefault()
    {
        $exception = new UserNotFoundException();

        self::assertNull($exception->getUsername());
    }

    /**
     * @group legacy
     */
    public function testUsernameNotFoundException()
    {
        $exception = new UsernameNotFoundException();
        self::assertInstanceOf(UserNotFoundException::class, $exception);

        $exception->setUsername('username');
        self::assertEquals('username', $exception->getUserIdentifier());
    }
}
