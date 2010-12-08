<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\User;

use Symfony\Component\Security\User\InMemoryUserProvider;
use Symfony\Component\Security\User\User;

class InMemoryUserProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $provider = new InMemoryUserProvider('foo', array(
            'fabien' => array(
                'password' => 'foo',
                'enabled'  => false,
                'roles'    => array('ROLE_USER'),
            ),
        ));

        list($user, $providerName) = $provider->loadUserByUsername('fabien');
        $this->assertSame('foo', $providerName);
        $this->assertEquals('foo', $user->getPassword());
        $this->assertEquals(array('ROLE_USER'), $user->getRoles());
        $this->assertFalse($user->isEnabled());
    }

    public function testCreateUser()
    {
        $provider = new InMemoryUserProvider('foo');
        $provider->createUser(new User('fabien', 'foo'));

        list($user, $providerName) = $provider->loadUserByUsername('fabien');
        $this->assertEquals('foo', $user->getPassword());
    }

    /**
     * @expectedException LogicException
     */
    public function testCreateUserAlreadyExist()
    {
        $provider = new InMemoryUserProvider('foo');
        $provider->createUser(new User('fabien', 'foo'));
        $provider->createUser(new User('fabien', 'foo'));
    }

    /**
     * @expectedException Symfony\Component\Security\Exception\UsernameNotFoundException
     */
    public function testLoadUserByUsernameDoesNotExist()
    {
        $provider = new InMemoryUserProvider('foo');
        $provider->loadUserByUsername('fabien');
    }
}
