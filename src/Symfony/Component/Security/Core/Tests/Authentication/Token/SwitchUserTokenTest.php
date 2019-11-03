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
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class SwitchUserTokenTest extends TestCase
{
    public function testSerialize()
    {
        $originalToken = new UsernamePasswordToken('user', 'foo', 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken('admin', 'bar', 'provider-key', ['ROLE_USER'], $originalToken);

        $unserializedToken = unserialize(serialize($token));

        $this->assertInstanceOf(SwitchUserToken::class, $unserializedToken);
        $this->assertSame('admin', $unserializedToken->getUsername());
        $this->assertSame('bar', $unserializedToken->getCredentials());
        $this->assertSame('provider-key', $unserializedToken->getProviderKey());
        $this->assertEquals(['ROLE_USER'], $unserializedToken->getRoleNames());

        $unserializedOriginalToken = $unserializedToken->getOriginalToken();

        $this->assertInstanceOf(UsernamePasswordToken::class, $unserializedOriginalToken);
        $this->assertSame('user', $unserializedOriginalToken->getUsername());
        $this->assertSame('foo', $unserializedOriginalToken->getCredentials());
        $this->assertSame('provider-key', $unserializedOriginalToken->getProviderKey());
        $this->assertEquals(['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'], $unserializedOriginalToken->getRoleNames());
    }

    public function testSetUserDoesNotDeauthenticate()
    {
        $impersonated = new class() implements UserInterface {
            public function getUsername()
            {
                return 'impersonated';
            }

            public function getPassword()
            {
                return null;
            }

            public function eraseCredentials()
            {
            }

            public function getRoles()
            {
                return ['ROLE_USER'];
            }

            public function getSalt()
            {
                return null;
            }
        };

        $originalToken = new UsernamePasswordToken('impersonator', 'foo', 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken($impersonated, 'bar', 'provider-key', ['ROLE_USER', 'ROLE_PREVIOUS_ADMIN'], $originalToken);
        $token->setUser($impersonated);
        $this->assertTrue($token->isAuthenticated());
    }
}
