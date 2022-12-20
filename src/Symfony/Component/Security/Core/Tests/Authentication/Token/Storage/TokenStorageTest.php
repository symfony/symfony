<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class TokenStorageTest extends TestCase
{
    public function testGetSetToken()
    {
        $tokenStorage = new TokenStorage();
        self::assertNull($tokenStorage->getToken());
        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password'), 'provider');
        $tokenStorage->setToken($token);
        self::assertSame($token, $tokenStorage->getToken());
    }
}
