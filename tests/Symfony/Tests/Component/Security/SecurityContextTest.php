<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security;

use Symfony\Component\Security\SecurityContext;

class SecurityContextTest extends \PHPUnit_Framework_TestCase
{
    public function testIsAuthenticated()
    {
        $context = new SecurityContext();

        $this->assertFalse($context->isAuthenticated());

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token->expects($this->once())->method('isAuthenticated')->will($this->returnValue(false));
        $context->setToken($token);
        $this->assertFalse($context->isAuthenticated());

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token->expects($this->once())->method('isAuthenticated')->will($this->returnValue(true));
        $context->setToken($token);
        $this->assertTrue($context->isAuthenticated());
    }

    public function testGetUser()
    {
        $context = new SecurityContext();

        $this->assertNull($context->getUser());

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token->expects($this->once())->method('getUser')->will($this->returnValue('foo'));
        $context->setToken($token);
        $this->assertEquals('foo', $context->getUser());
    }

    public function testVote()
    {
        $context = new SecurityContext();

        $this->assertFalse($context->vote('ROLE_FOO'));

        $context->setToken($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertFalse($context->vote('ROLE_FOO'));

        $manager = $this->getMock('Symfony\Component\Security\Authorization\AccessDecisionManager');
        $manager->expects($this->once())->method('decide')->will($this->returnValue(false));
        $context = new SecurityContext($manager);
        $context->setToken($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));

        $this->assertFalse($context->vote('ROLE_FOO'));

        $manager = $this->getMock('Symfony\Component\Security\Authorization\AccessDecisionManager');
        $manager->expects($this->once())->method('decide')->will($this->returnValue(true));
        $context = new SecurityContext($manager);
        $context->setToken($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));

        $this->assertTrue($context->vote('ROLE_FOO'));
    }

    public function testGetSetToken()
    {
        $context = new SecurityContext();
        $this->assertNull($context->getToken());

        $context->setToken($token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface'));
        $this->assertSame($token, $context->getToken());
    }
}
