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
use Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures\CustomUser;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class SwitchUserTokenTest extends TestCase
{
    public function testSerialize()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('user', 'foo', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']), 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken(new InMemoryUser('admin', 'bar', ['ROLE_USER']), 'provider-key', ['ROLE_USER'], $originalToken, 'https://symfony.com/blog');

        $unserializedToken = unserialize(serialize($token));

        self::assertInstanceOf(SwitchUserToken::class, $unserializedToken);
        self::assertSame('admin', $unserializedToken->getUserIdentifier());
        self::assertSame('provider-key', $unserializedToken->getFirewallName());
        self::assertEquals(['ROLE_USER'], $unserializedToken->getRoleNames());
        self::assertSame('https://symfony.com/blog', $unserializedToken->getOriginatedFromUri());

        $unserializedOriginalToken = $unserializedToken->getOriginalToken();

        self::assertInstanceOf(UsernamePasswordToken::class, $unserializedOriginalToken);
        self::assertSame('user', $unserializedOriginalToken->getUserIdentifier());
        self::assertSame('provider-key', $unserializedOriginalToken->getFirewallName());
        self::assertEquals(['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'], $unserializedOriginalToken->getRoleNames());
    }

    /**
     * @group legacy
     */
    public function testLegacySerialize()
    {
        $originalToken = new UsernamePasswordToken('user', 'foo', 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken('admin', 'bar', 'provider-key', ['ROLE_USER'], $originalToken, 'https://symfony.com/blog');

        $unserializedToken = unserialize(serialize($token));

        self::assertInstanceOf(SwitchUserToken::class, $unserializedToken);
        self::assertSame('admin', $unserializedToken->getUserIdentifier());
        self::assertSame('bar', $unserializedToken->getCredentials());
        self::assertSame('provider-key', $unserializedToken->getFirewallName());
        self::assertEquals(['ROLE_USER'], $unserializedToken->getRoleNames());
        self::assertSame('https://symfony.com/blog', $unserializedToken->getOriginatedFromUri());

        $unserializedOriginalToken = $unserializedToken->getOriginalToken();

        self::assertInstanceOf(UsernamePasswordToken::class, $unserializedOriginalToken);
        self::assertSame('user', $unserializedOriginalToken->getUserIdentifier());
        self::assertSame('foo', $unserializedOriginalToken->getCredentials());
        self::assertSame('provider-key', $unserializedOriginalToken->getFirewallName());
        self::assertEquals(['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'], $unserializedOriginalToken->getRoleNames());
    }

    /**
     * @group legacy
     */
    public function testSetUserDoesNotDeauthenticate()
    {
        $impersonated = new class() implements UserInterface {
            public function getUsername()
            {
                return 'impersonated';
            }

            public function getUserIdentifier()
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

        $originalToken = new UsernamePasswordToken(new InMemoryUser('impersonator', '', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']), 'foo', 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken($impersonated, 'bar', 'provider-key', ['ROLE_USER', 'ROLE_PREVIOUS_ADMIN'], $originalToken);
        $token->setUser($impersonated);
        self::assertTrue($token->isAuthenticated());
    }

    public function testSerializeNullImpersonateUrl()
    {
        $originalToken = new UsernamePasswordToken(new InMemoryUser('user', 'foo', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']), 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken(new InMemoryUser('admin', 'bar', ['ROLE_USER']), 'provider-key', ['ROLE_USER'], $originalToken);

        $unserializedToken = unserialize(serialize($token));

        self::assertNull($unserializedToken->getOriginatedFromUri());
    }

    /**
     * @group legacy
     */
    public function testLegacySerializeNullImpersonateUrl()
    {
        $originalToken = new UsernamePasswordToken('user', 'foo', 'provider-key', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']);
        $token = new SwitchUserToken('admin', 'bar', 'provider-key', ['ROLE_USER'], $originalToken);

        $unserializedToken = unserialize(serialize($token));

        self::assertNull($unserializedToken->getOriginatedFromUri());
    }

    /**
     * Tests if an old version of SwitchUserToken can still be unserialized.
     *
     * The fixture was generated by running the following code with Symfony 4.4 and PHP 7.2.
     *
     * serialize(
     *     new SwitchUserToken(
     *         new CustomUser('john', ['ROLE_USER']),
     *         ['foo' => 'bar'],
     *         'main', ['ROLE_USER'],
     *         new UsernamePasswordToken(
     *             new CustomUser('jane', ['ROLE_USER']),
     *             ['foo' => 'bar'],
     *             'main',
     *             ['ROLE_USER']
     *         )
     *     )
     * )
     *
     * @group legacy
     */
    public function testUnserializeOldToken()
    {
        /** @var SwitchUserToken $token */
        $token = unserialize(file_get_contents(__DIR__.'/Fixtures/switch-user-token-4.4.txt'));

        self::assertInstanceOf(SwitchUserToken::class, $token);
        self::assertInstanceOf(UsernamePasswordToken::class, $token->getOriginalToken());
        self::assertInstanceOf(CustomUser::class, $token->getUser());
        self::assertSame('john', $token->getUserIdentifier());
        self::assertSame(['foo' => 'bar'], $token->getCredentials());
        self::assertSame('main', $token->getFirewallName());
        self::assertEquals(['ROLE_USER'], $token->getRoleNames());
        self::assertNull($token->getOriginatedFromUri());
    }
}
