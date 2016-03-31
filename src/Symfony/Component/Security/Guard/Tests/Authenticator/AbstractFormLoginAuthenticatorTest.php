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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;

class AbstractFormLoginAuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyWithLoginUrl()
    {
        $request = new Request();
        $request->setSession($this->getMock('Symfony\Component\HttpFoundation\Session\Session'));

        $authenticator = new LegacyFormLoginAuthenticator();
        /** @var RedirectResponse $actualResponse */
        $actualResponse = $authenticator->onAuthenticationSuccess(
            $request,
            $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface'),
            'provider_key'
        );

        $this->assertEquals('/default_url', $actualResponse->getTargetUrl());
    }
}

class LegacyFormLoginAuthenticator extends AbstractFormLoginAuthenticator
{
    protected function getDefaultSuccessRedirectUrl()
    {
        return '/default_url';
    }

    protected function getLoginUrl()
    {
    }

    public function getCredentials(Request $request)
    {
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
    }
}
