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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * @group legacy
 */
class LegacySecurityContextTest extends TestCase
{
    private $tokenStorage;
    private $authorizationChecker;
    private $securityContext;

    protected function setUp()
    {
        $this->tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $this->authorizationChecker = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface')->getMock();
        $this->securityContext = new SecurityContext($this->tokenStorage, $this->authorizationChecker);
    }

    public function testGetTokenDelegation()
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        $this->assertSame($token, $this->securityContext->getToken());
    }

    public function testSetTokenDelegation()
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();

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
     * Test dedicated to check if the backwards compatibility is still working.
     */
    public function testOldConstructorSignature()
    {
        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();

        $this->assertInstanceOf('Symfony\Component\Security\Core\SecurityContext', new SecurityContext($authenticationManager, $accessDecisionManager));
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
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();
        $authorizationChecker = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface')->getMock();
        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();

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

    /**
     * Test if the BC Layer is working as intended.
     */
    public function testConstantSync()
    {
        $this->assertSame(Security::ACCESS_DENIED_ERROR, SecurityContextInterface::ACCESS_DENIED_ERROR);
        $this->assertSame(Security::AUTHENTICATION_ERROR, SecurityContextInterface::AUTHENTICATION_ERROR);
        $this->assertSame(Security::LAST_USERNAME, SecurityContextInterface::LAST_USERNAME);
    }
}
