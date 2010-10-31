<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authentication\Token;

use Symfony\Component\Security\Authentication\Token\Token as BaseToken;
use Symfony\Component\Security\Role\Role;

class Token extends BaseToken
{
}

class TokenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Security\Authentication\Token\Token::__construct
     */
    public function testConstructor()
    {
        $token = new Token(array('ROLE_FOO'));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token = new Token(array(new Role('ROLE_FOO')));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token = new Token(array(new Role('ROLE_FOO'), 'ROLE_BAR'));
        $this->assertEquals(array(new Role('ROLE_FOO'), new Role('ROLE_BAR')), $token->getRoles());
    }

    /**
     * @covers Symfony\Component\Security\Authentication\Token\Token::addRole
     * @covers Symfony\Component\Security\Authentication\Token\Token::getRoles
     */
    public function testAddRole()
    {
        $token = new Token();
        $token->addRole(new Role('ROLE_FOO'));
        $this->assertEquals(array(new Role('ROLE_FOO')), $token->getRoles());

        $token->addRole(new Role('ROLE_BAR'));
        $this->assertEquals(array(new Role('ROLE_FOO'), new Role('ROLE_BAR')), $token->getRoles());
    }

    /**
     * @covers Symfony\Component\Security\Authentication\Token\Token::isAuthenticated
     * @covers Symfony\Component\Security\Authentication\Token\Token::setAuthenticated
     */
    public function testAuthenticatedFlag()
    {
        $token = new Token();
        $this->assertFalse($token->isAuthenticated());

        $token->setAuthenticated(true);
        $this->assertTrue($token->isAuthenticated());

        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    /**
     * @covers Symfony\Component\Security\Authentication\Token\Token::isImmutable
     * @covers Symfony\Component\Security\Authentication\Token\Token::setImmutable
     */
    public function testImmutableFlag()
    {
        $token = new Token();
        $this->assertFalse($token->isImmutable());

        $token->setImmutable(true);
        $this->assertTrue($token->isImmutable());

        $token->setImmutable(false);
        $this->assertFalse($token->isImmutable());
    }
}
