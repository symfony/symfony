<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication\Token;

use Symfony\Component\Security\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Role\Role;

class PreAuthenticatedTokenTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $token = new PreAuthenticatedToken('foo', 'bar');
        $this->assertFalse($token->isAuthenticated());

        $token = new PreAuthenticatedToken('foo', 'bar', array('ROLE_FOO'));
        $this->assertTrue($token->isAuthenticated());
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());
    }

    public function testGetCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar');
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testGetUser()
    {
        $token = new PreAuthenticatedToken('foo', 'bar');
        $this->assertEquals('foo', $token->getUser());
    }

    public function testEraseCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar');
        $token->eraseCredentials();
        $this->assertEquals('', $token->getCredentials());
    }
}
