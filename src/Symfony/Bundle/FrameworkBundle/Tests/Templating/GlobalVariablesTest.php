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

    public function setUp()
    {
        $this->container = new Container();
        $this->globals = new GlobalVariables($this->container);
    }

    public function testGetSecurity()
    {
        $securityContext = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');

        $this->assertNull($this->globals->getSecurity());
        $this->container->set('security.context', $securityContext);
        $this->assertSame($securityContext, $this->globals->getSecurity());
    }

    public function testGetUser()
    {
        // missing test cases to return null, only happy flow tested
        $securityContext = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $this->container->set('security.token_storage', $securityContext);

        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($user));

        $securityContext
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->assertSame($user, $this->globals->getUser());
    }

    public function testGetRequest()
    {
        $this->markTestIncomplete();
    }

    public function testGetSession()
    {
        $this->markTestIncomplete();
    }

    public function testGetEnvironment()
    {
        $this->markTestIncomplete();
    }

    public function testGetDubug()
    {
        $this->markTestIncomplete();
    }
}
