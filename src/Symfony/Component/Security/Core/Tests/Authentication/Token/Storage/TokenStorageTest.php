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

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class TokenStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSetToken()
    {
        $tokenStorage = new TokenStorage();
        $this->assertNull($tokenStorage->getToken());
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenStorage->setToken($token);
        $this->assertSame($token, $tokenStorage->getToken());
    }
}
