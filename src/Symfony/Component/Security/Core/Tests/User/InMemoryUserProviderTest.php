<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\User;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;

class InMemoryUserProviderTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $provider = $this->createProvider();

        $user = $provider->loadUserByIdentifier('fabien');
        self::assertEquals('foo', $user->getPassword());
        self::assertEquals(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isEnabled());
    }

    public function testRefresh()
    {
        $user = new InMemoryUser('fabien', 'bar');

        $provider = $this->createProvider();

        $refreshedUser = $provider->refreshUser($user);
        self::assertEquals('foo', $refreshedUser->getPassword());
        self::assertEquals(['ROLE_USER'], $refreshedUser->getRoles());
        self::assertFalse($refreshedUser->isEnabled());
    }

    /**
     * @group legacy
     */
    public function testRefreshWithLegacyUser()
    {
        $user = new User('fabien', 'bar');

        $provider = $this->createProvider();

        $refreshedUser = $provider->refreshUser($user);
        self::assertEquals('foo', $refreshedUser->getPassword());
        self::assertEquals(['ROLE_USER'], $refreshedUser->getRoles());
        self::assertFalse($refreshedUser->isEnabled());
        self::assertFalse($refreshedUser->isCredentialsNonExpired());
    }

    protected function createProvider(): InMemoryUserProvider
    {
        return new InMemoryUserProvider([
            'fabien' => [
                'password' => 'foo',
                'enabled' => false,
                'roles' => ['ROLE_USER'],
            ],
        ]);
    }

    public function testCreateUser()
    {
        $provider = new InMemoryUserProvider();
        $provider->createUser(new InMemoryUser('fabien', 'foo'));

        $user = $provider->loadUserByIdentifier('fabien');
        self::assertEquals('foo', $user->getPassword());
    }

    public function testCreateUserAlreadyExist()
    {
        self::expectException(\LogicException::class);
        $provider = new InMemoryUserProvider();
        $provider->createUser(new InMemoryUser('fabien', 'foo'));
        $provider->createUser(new InMemoryUser('fabien', 'foo'));
    }

    public function testLoadUserByUsernameDoesNotExist()
    {
        self::expectException(UserNotFoundException::class);
        $provider = new InMemoryUserProvider();
        $provider->loadUserByIdentifier('fabien');
    }
}
