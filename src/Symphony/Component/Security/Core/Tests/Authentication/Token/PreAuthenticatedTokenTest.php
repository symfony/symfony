<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authentication\Token;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symphony\Component\Security\Core\Role\Role;

class PreAuthenticatedTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $this->assertFalse($token->isAuthenticated());

        $token = new PreAuthenticatedToken('foo', 'bar', 'key', array('ROLE_FOO'));
        $this->assertTrue($token->isAuthenticated());
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());
        $this->assertEquals('key', $token->getProviderKey());
    }

    public function testGetCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testGetUser()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $this->assertEquals('foo', $token->getUser());
    }

    public function testEraseCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $token->eraseCredentials();
        $this->assertEquals('', $token->getCredentials());
    }
}
