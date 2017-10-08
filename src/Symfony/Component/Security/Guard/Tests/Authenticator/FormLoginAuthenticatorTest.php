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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;

/**
 * @author Jean Pasdeloup <jpasdeloup@sedona.fr>
 */
class FormLoginAuthenticatorTest extends TestCase
{
    private $requestWithoutSession;
    private $requestWithSession;
    private $authenticator;

    const LOGIN_URL = 'http://login';
    const DEFAULT_SUCCESS_URL = 'http://defaultsuccess';
    const CUSTOM_SUCCESS_URL = 'http://customsuccess';

    public function testAuthenticationFailureWithoutSession()
    {
        $failureResponse = $this->authenticator->onAuthenticationFailure($this->requestWithoutSession, new AuthenticationException());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\RedirectResponse', $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testAuthenticationFailureWithSession()
    {
        $this->requestWithSession->getSession()
            ->expects($this->once())
            ->method('set');

        $failureResponse = $this->authenticator->onAuthenticationFailure($this->requestWithSession, new AuthenticationException());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\RedirectResponse', $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testRememberMe()
    {
        $doSupport = $this->authenticator->supportsRememberMe();

        $this->assertTrue($doSupport);
    }

    public function testStartWithoutSession()
    {
        $failureResponse = $this->authenticator->start($this->requestWithoutSession, new AuthenticationException());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\RedirectResponse', $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    public function testStartWithSession()
    {
        $failureResponse = $this->authenticator->start($this->requestWithSession, new AuthenticationException());

        $this->assertInstanceOf('Symfony\\Component\\HttpFoundation\\RedirectResponse', $failureResponse);
        $this->assertEquals(self::LOGIN_URL, $failureResponse->getTargetUrl());
    }

    protected function setUp()
    {
        $this->requestWithoutSession = new Request(array(), array(), array(), array(), array(), array());
        $this->requestWithSession = new Request(array(), array(), array(), array(), array(), array());

        $session = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\Session\\SessionInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->requestWithSession->setSession($session);

        $this->authenticator = new TestFormLoginAuthenticator();
        $this->authenticator
            ->setLoginUrl(self::LOGIN_URL)
            ->setDefaultSuccessRedirectUrl(self::DEFAULT_SUCCESS_URL)
        ;
    }

    protected function tearDown()
    {
        $this->request = null;
        $this->requestWithSession = null;
    }
}

class TestFormLoginAuthenticator extends AbstractFormLoginAuthenticator
{
    private $loginUrl;
    private $defaultSuccessRedirectUrl;

    public function supports(Request $request)
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
    }

    /**
     * @param mixed $defaultSuccessRedirectUrl
     *
     * @return TestFormLoginAuthenticator
     */
    public function setDefaultSuccessRedirectUrl($defaultSuccessRedirectUrl)
    {
        $this->defaultSuccessRedirectUrl = $defaultSuccessRedirectUrl;

        return $this;
    }

    /**
     * @param mixed $loginUrl
     *
     * @return TestFormLoginAuthenticator
     */
    public function setLoginUrl($loginUrl)
    {
        $this->loginUrl = $loginUrl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getLoginUrl()
    {
        return $this->loginUrl;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSuccessRedirectUrl()
    {
        return $this->defaultSuccessRedirectUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(Request $request)
    {
        return 'credentials';
    }

    /**
     * {@inheritdoc}
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }
}
