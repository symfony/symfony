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
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class PreAuthenticatedTokenTest extends TestCase
{
    public function testConstructor()
    {
        $token = new PreAuthenticatedToken(new InMemoryUser('foo', 'bar', ['ROLE_FOO']), 'key', ['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testLegacyConstructor()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key', ['ROLE_FOO']);
        $this->assertEquals(['ROLE_FOO'], $token->getRoleNames());
        $this->assertEquals('key', $token->getFirewallName());
    }

    /**
     * @group legacy
     */
    public function testGetCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testGetUser()
    {
        $token = new PreAuthenticatedToken($user = new InMemoryUser('foo', 'bar'), 'key');
        $this->assertEquals($user, $token->getUser());
    }

    /**
     * @group legacy
     */
    public function testEraseCredentials()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $token->eraseCredentials();
        $this->assertNull($token->getCredentials());
    }

    /**
     * @group legacy
     */
    public function testIsAuthenticated()
    {
        $token = new PreAuthenticatedToken('foo', 'bar', 'key');
        $this->assertFalse($token->isAuthenticated());
    }
}
