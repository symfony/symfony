<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Provider;

use Symfony\Component\Security\Guard\Provider\GuardAuthenticationProvider;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

/**
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
class GuardAuthenticationProviderTest extends \PHPUnit_Framework_TestCase
{
    private $userProvider;
    private $userChecker;
    private $preAuthenticationToken;

    public function testAuthenticate()
    {
        $providerKey = 'my_cool_firewall';

        $authenticatorA = $this->getMock('Symfony\Component\Security\Guard\GuardAuthenticatorInterface');
        $authenticatorB = $this->getMock('Symfony\Component\Security\Guard\GuardAuthenticatorInterface');
        $authenticatorC = $this->getMock('Symfony\Component\Security\Guard\GuardAuthenticatorInterface');
        $authenticators = array($authenticatorA, $authenticatorB, $authenticatorC);

        // called 2 times - for authenticator A and B (stops on B because of match)
        $this->preAuthenticationToken->expects($this->exactly(2))
            ->method('getGuardProviderKey')
            // it will return the "1" index, which will match authenticatorB
            ->will($this->returnValue('my_cool_firewall_1'));

        $enteredCredentials = array(
            'username' => '_weaverryan_test_user',
            'password' => 'guard_auth_ftw',
        );
        $this->preAuthenticationToken->expects($this->atLeastOnce())
            ->method('getCredentials')
            ->will($this->returnValue($enteredCredentials));

        // authenticators A and C are never called
        $authenticatorA->expects($this->never())
            ->method('getUser');
        $authenticatorC->expects($this->never())
            ->method('getUser');

        $mockedUser = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $authenticatorB->expects($this->once())
            ->method('getUser')
            ->with($enteredCredentials, $this->userProvider)
            ->will($this->returnValue($mockedUser));
        // checkCredentials is called
        $authenticatorB->expects($this->once())
            ->method('checkCredentials')
            ->with($enteredCredentials, $mockedUser)
            // authentication works!
            ->will($this->returnValue(true));
        $authedToken = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $authenticatorB->expects($this->once())
            ->method('createAuthenticatedToken')
            ->with($mockedUser, $providerKey)
            ->will($this->returnValue($authedToken));

        // user checker should be called
        $this->userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->with($mockedUser);
        $this->userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->with($mockedUser);

        $provider = new GuardAuthenticationProvider($authenticators, $this->userProvider, $providerKey, $this->userChecker);
        $actualAuthedToken = $provider->authenticate($this->preAuthenticationToken);
        $this->assertSame($authedToken, $actualAuthedToken);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\BadCredentialsException
     */
    public function testCheckCredentialsReturningNonTrueFailsAuthentication()
    {
        $providerKey = 'my_uncool_firewall';

        $authenticator = $this->getMock('Symfony\Component\Security\Guard\GuardAuthenticatorInterface');

        // make sure the authenticator is used
        $this->preAuthenticationToken->expects($this->any())
            ->method('getGuardProviderKey')
            // the 0 index, to match the only authenticator
            ->will($this->returnValue('my_uncool_firewall_0'));

        $this->preAuthenticationToken->expects($this->atLeastOnce())
            ->method('getCredentials')
            ->will($this->returnValue('non-null-value'));

        $mockedUser = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $authenticator->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($mockedUser));
        // checkCredentials is called
        $authenticator->expects($this->once())
            ->method('checkCredentials')
            // authentication fails :(
            ->will($this->returnValue(null));

        $provider = new GuardAuthenticationProvider(array($authenticator), $this->userProvider, $providerKey, $this->userChecker);
        $provider->authenticate($this->preAuthenticationToken);
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationExpiredException
     */
    public function testGuardWithNoLongerAuthenticatedTriggersLogout()
    {
        $providerKey = 'my_firewall_abc';

        // create a token and mark it as NOT authenticated anymore
        // this mimics what would happen if a user "changed" between request
        $mockedUser = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $token = new PostAuthenticationGuardToken($mockedUser, $providerKey, array('ROLE_USER'));
        $token->setAuthenticated(false);

        $provider = new GuardAuthenticationProvider(array(), $this->userProvider, $providerKey, $this->userChecker);
        $actualToken = $provider->authenticate($token);
    }

    protected function setUp()
    {
        $this->userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $this->userChecker = $this->getMock('Symfony\Component\Security\Core\User\UserCheckerInterface');
        $this->preAuthenticationToken = $this->getMockBuilder('Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken')
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        $this->userProvider = null;
        $this->userChecker = null;
        $this->preAuthenticationToken = null;
    }
}
