<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;

class GlobalVariablesTest extends TestCase
{
    private $container;
    private $globals;

    protected function setUp()
    {
        $this->container = new Container();
        $this->globals = new GlobalVariables($this->container);
    }

    public function testGetTokenNoTokenStorage()
    {
        $this->assertNull($this->globals->getToken());
    }

    public function testGetTokenNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->container->set('security.token_storage', $tokenStorage);
        $this->assertNull($this->globals->getToken());
    }

    public function testGetToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $this->container->set('security.token_storage', $tokenStorage);

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue('token'));

        $this->assertSame('token', $this->globals->getToken());
    }

    public function testGetUserNoTokenStorage()
    {
        $this->assertNull($this->globals->getUser());
    }

    public function testGetUserNoToken()
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->container->set('security.token_storage', $tokenStorage);
        $this->assertNull($this->globals->getUser());
    }

    /**
     * @dataProvider getUserProvider
     */
    public function testGetUser($user, $expectedUser)
    {
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $this->container->set('security.token_storage', $tokenStorage);

        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($user));

        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->assertSame($expectedUser, $this->globals->getUser());
    }

    public function getUserProvider()
    {
        $user = $this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock();
        $std = new \stdClass();
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        return [
            [$user, $user],
            [$std, $std],
            [$token, $token],
            ['Anon.', null],
            [null, null],
            [10, null],
            [true, null],
        ];
    }
}
