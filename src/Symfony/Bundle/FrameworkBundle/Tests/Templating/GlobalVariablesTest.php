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

    /**
     * @group legacy
     */
    public function testLegacyGetSecurity()
    {
        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');

        $this->assertNull($this->globals->getSecurity());
        $this->container->set('security.context', $securityContext);
        $this->assertSame($securityContext, $this->globals->getSecurity());
    }

    public function testGetUserNoTokenStorage()
    {
        $this->assertNull($this->globals->getUser());
    }

    public function testGetUserNoToken()
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->container->set('security.token_storage', $tokenStorage);
        $this->assertNull($this->globals->getUser());
    }

    /**
     * @dataProvider getUserProvider
     */
    public function testGetUser($user, $expectedUser)
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

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
        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $std = new \stdClass();
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        return array(
            array($user, $user),
            array($std, $std),
            array($token, $token),
            array('Anon.', null),
            array(null, null),
            array(10, null),
            array(true, null),
        );
    }
}
