<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UsernamePasswordTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', 'bar', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testLegacyConstructor()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key', ['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertEquals('bar', $token->getCredentials());
        $this->assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testIsAuthenticated()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $this->assertFalse($token->isAuthenticated());

        $token = new UsernamePasswordToken('foo', 'bar', 'key', ['ROLE_FOO']);
        $this->assertTrue($token->isAuthenticated());
    }

    /**
     * @group legacy
     */
    public function testSetAuthenticatedToTrue()
    {
        $this->expectException(\LogicException::class);
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $token->setAuthenticated(true);
    }

    /**
     * @group legacy
     */
    public function testSetAuthenticatedToFalse()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $token->setAuthenticated(false);
        $this->assertFalse($token->isAuthenticated());
    }

    /**
     * @group legacy
     */
    public function testEraseCredentials()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $token->eraseCredentials();
        $this->assertEquals('', $token->getCredentials());
    }

    public function testToString()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', '', ['A', 'B']), 'foo', ['A', 'B']);
        $this->assertEquals('UsernamePasswordToken(user="foo", authenticated=true, roles="A, B")', (string) $token);
    }
}
