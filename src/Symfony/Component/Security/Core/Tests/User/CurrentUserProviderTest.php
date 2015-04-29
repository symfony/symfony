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

use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\CurrentUserProvider;
use Symfony\Component\Security\Core\User\User;

class CurrentUserProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUser()
    {
        $user = new User('user', 'pass');
        $token = new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'));

        $service = new CurrentUserProvider($this->getTokenStorage($token));

        $this->assertSame($service->getUser(), $user);
    }

    public function testGetUserAnonymousUserConvertedToNull()
    {
        $token = new AnonymousToken('default', 'anon.');

        $service = new CurrentUserProvider($this->getTokenStorage($token));

        $this->assertNull($service->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $service = new CurrentUserProvider($this->getTokenStorage(null));

        $this->assertNull($service->getUser());
    }

    /**
     * @param $token
     *
     * @return TokenStorageInterface
     */
    private function getTokenStorage($token = null)
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage');
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        return $tokenStorage;
    }
}
