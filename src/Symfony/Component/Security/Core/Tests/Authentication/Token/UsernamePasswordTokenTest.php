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
        self::assertEquals(['ROLE_FOO'], $token->getRoleNames());
        self::assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testLegacyConstructor()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key', ['ROLE_FOO']);
        self::assertEquals(['ROLE_FOO'], $token->getRoleNames());
        self::assertEquals('bar', $token->getCredentials());
        self::assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testIsAuthenticated()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        self::assertFalse($token->isAuthenticated());

        $token = new UsernamePasswordToken('foo', 'bar', 'key', ['ROLE_FOO']);
        self::assertTrue($token->isAuthenticated());
    }

    /**
     * @group legacy
     */
    public function testSetAuthenticatedToTrue()
    {
        self::expectException(\LogicException::class);
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
        self::assertFalse($token->isAuthenticated());
    }

    /**
     * @group legacy
     */
    public function testEraseCredentials()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $token->eraseCredentials();
        self::assertEquals('', $token->getCredentials());
    }

    public function testToString()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('foo', '', ['A', 'B']), 'foo', ['A', 'B']);
        self::assertEquals('UsernamePasswordToken(user="foo", authenticated=true, roles="A, B")', (string) $token);
    }
}
