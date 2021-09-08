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
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;

class InMemoryUserProviderTest extends TestCase
{
    public function testConstructor()
    {
        $provider = $this->createProvider();

        $user = $provider->loadUserByIdentifier('fabien');
        $this->assertEquals('foo', $user->getPassword());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
        $this->assertFalse($user->isEnabled());
    }

    public function testRefresh()
    {
        $user = new InMemoryUser('fabien', 'bar');

        $provider = $this->createProvider();

        $refreshedUser = $provider->refreshUser($user);
        $this->assertEquals('foo', $refreshedUser->getPassword());
        $this->assertEquals(['ROLE_USER'], $refreshedUser->getRoles());
        $this->assertFalse($refreshedUser->isEnabled());
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
        $this->assertEquals('foo', $user->getPassword());
    }

    public function testCreateUserAlreadyExist()
    {
        $this->expectException(\LogicException::class);
        $provider = new InMemoryUserProvider();
        $provider->createUser(new InMemoryUser('fabien', 'foo'));
        $provider->createUser(new InMemoryUser('fabien', 'foo'));
    }

    public function testLoadUserByUsernameDoesNotExist()
    {
        $this->expectException(UserNotFoundException::class);
        $provider = new InMemoryUserProvider();
        $provider->loadUserByIdentifier('fabien');
    }
}
