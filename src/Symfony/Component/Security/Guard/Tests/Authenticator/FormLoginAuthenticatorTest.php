<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Jean Pasdeloup <jpasdeloup@sedona.fr>
 */
class FormLoginAuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    private $requestWithoutSession;
    private $requestWithSession;

    const LOGIN_URL = "http://login";
    const DEFAULT_SUCCESS_URL = "http://defaultsuccess";
    const CUSTOM_SUCCESS_URL = "http://customsuccess";

    public function testAuthenticateWithoutSession()
    {
        $authenticator = new MockFormLoginAuthenticator();
        $authenticator
            ->setLoginUrl(self::LOGIN_URL)
            ->setDefaultSuccessRedirectUrl(self::DEFAULT_SUCCESS_URL)
        ;

        // onAuthenticationFailure
        $failureResponse = $authenticator->onAuthenticationFailure($this->requestWithoutSession, new AuthenticationException());
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());

        // onAuthenticationSuccess
        $token = $this->getMockBuilder("Symfony\\Component\\Security\\Core\\Authentication\\Token\\TokenInterface")
            ->disableOriginalConstructor()
            ->getMock();
        $redirectResponse = $authenticator->onAuthenticationSuccess($this->requestWithoutSession, $token, "providerkey");
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $redirectResponse);
        $this->assertEquals(self::DEFAULT_SUCCESS_URL, $redirectResponse->getTargetUrl());
        
        // supportsRememberMe
        $this->assertTrue($authenticator->supportsRememberMe());
        
        // start
        $failureResponse = $authenticator->start($this->requestWithoutSession, new AuthenticationException());
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testAuthenticateWithSession()
    {
        $authenticator = new MockFormLoginAuthenticator();
        $authenticator
            ->setLoginUrl(self::LOGIN_URL)
            ->setDefaultSuccessRedirectUrl(self::DEFAULT_SUCCESS_URL)
        ;

        // onAuthenticationFailure
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('set')
            ;
        $failureResponse = $authenticator->onAuthenticationFailure($this->requestWithSession, new AuthenticationException());
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());

        // onAuthenticationSuccess
        $token = $this->getMockBuilder("Symfony\\Component\\Security\\Core\\Authentication\\Token\\TokenInterface")
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(self::CUSTOM_SUCCESS_URL));
        ;
        $redirectResponse = $authenticator->onAuthenticationSuccess($this->requestWithSession, $token, "providerkey");
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $redirectResponse);
        $this->assertEquals(self::CUSTOM_SUCCESS_URL, $redirectResponse->getTargetUrl());

        // supportsRememberMe
        $this->assertTrue($authenticator->supportsRememberMe());

        // start
        $failureResponse = $authenticator->start($this->requestWithSession, new AuthenticationException());
        $this->assertInstanceOf("Symfony\\Component\\HttpFoundation\\RedirectResponse", $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    protected function setUp()
    {
        $this->requestWithoutSession = new Request(array(), array(), array(), array(), array(), array());
        $this->requestWithSession = new Request(array(), array(), array(), array(), array(), array());
        $session = $this->getMockBuilder("Symfony\\Component\\HttpFoundation\\Session\\SessionInterface")
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->setSession($session);
    }

    protected function tearDown()
    {
        $this->request = null;
        $this->requestWithSession = null;
    }
}
