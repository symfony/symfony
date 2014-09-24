<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\SecurityContext;

class SecurityContextTest extends \PHPUnit_Framework_TestCase
{
    private $tokenStorage;
    private $authorizationChecker;
    private $securityContext;

    public function setUp()
    {
        $this->tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->authorizationChecker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');
        $this->securityContext = new SecurityContext($this->tokenStorage, $this->authorizationChecker);
    }

    public function testGetTokenDelegation()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->assertTrue($token === $this->securityContext->getToken());
    }

    public function testSetTokenDelegation()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $this->tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($token);

        $this->securityContext->setToken($token);
    }

    /**
     * @dataProvider isGrantedDelegationProvider
     */
    public function testIsGrantedDelegation($attributes, $object, $return)
    {
        $this->authorizationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with($attributes, $object)
            ->will($this->returnValue($return));

        $this->assertEquals($return, $this->securityContext->isGranted($attributes, $object));
    }

    public function isGrantedDelegationProvider()
    {
        return array(
            array(array(), new \stdClass(), true),
            array(array('henk'), new \stdClass(), false),
            array(null, new \stdClass(), false),
            array('henk', null, true),
            array(array(1), 'henk', true),
        );
    }

    /**
     * Test dedicated to check if the backwards compatibility is still working
     */
    public function testOldConstructorSignature()
    {
        $authenticationManager = $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');
        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');
        new SecurityContext($authenticationManager, $accessDecisionManager);
    }

    /**
     * @dataProvider oldConstructorSignatureFailuresProvider
     * @expectedException \BadMethodCallException
     */
    public function testOldConstructorSignatureFailures($first, $second)
    {
        new SecurityContext($first, $second);
    }

    public function oldConstructorSignatureFailuresProvider()
    {
        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $authorizationChecker = $this->getMock('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface');
        $authenticationManager = $this->getMock('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface');
        $accessDecisionManager = $this->getMock('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface');

        return array(
            array(new \stdClass(), new \stdClass()),
            array($tokenStorage, $accessDecisionManager),
            array($accessDecisionManager, $tokenStorage),
            array($authorizationChecker, $accessDecisionManager),
            array($accessDecisionManager, $authorizationChecker),
            array($tokenStorage, $accessDecisionManager),
            array($authenticationManager, $authorizationChecker),
            array('henk', 'hans'),
            array(null, false),
            array(true, null),
        );
    }
}
