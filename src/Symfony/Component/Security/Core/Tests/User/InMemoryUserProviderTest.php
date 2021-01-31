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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;

class InMemoryUserProviderTest extends TestCase
{
    /**
     * @
     */
    public function testConstructor()
    {
        $provider = $this->createProvider();

        $user = $provider->loadUserByUsername('fabien');
        $this->assertEquals('foo', $user->getPassword());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testRefresh()
    {
        $user = new InMemoryUser('fabien', 'bar');

        $provider = $this->createProvider();

        $refreshedUser = $provider->refreshUser($user);
        $this->assertEquals('foo', $refreshedUser->getPassword());
        $this->assertEquals(['ROLE_USER'], $refreshedUser->getRoles());
    }

    protected function createProvider(): InMemoryUserProvider
    {
        return new InMemoryUserProvider([
            'fabien' => [
                'password' => 'foo',
                'roles' => ['ROLE_USER'],
            ],
        ]);
    }

    public function testCreateUser()
    {
        $provider = new InMemoryUserProvider();
        $provider->createUser(new InMemoryUser('fabien', 'foo'));

        $user = $provider->loadUserByUsername('fabien');
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
        $this->expectException(UsernameNotFoundException::class);
        $provider = new InMemoryUserProvider();
        $provider->loadUserByUsername('fabien');
    }
}
