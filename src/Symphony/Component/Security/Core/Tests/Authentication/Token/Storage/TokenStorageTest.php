<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authentication\Token\Storage;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class TokenStorageTest extends TestCase
{
    public function testGetSetToken()
    {
        $tokenStorage = new TokenStorage();
        $this->assertNull($tokenStorage->getToken());
        $token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $tokenStorage->setToken($token);
        $this->assertSame($token, $tokenStorage->getToken());
    }
}
