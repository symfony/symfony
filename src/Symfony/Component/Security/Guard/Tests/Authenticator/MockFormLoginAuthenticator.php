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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;

/**
 * @author Jean Pasdeloup <jpasdeloup@sedona.fr>
 */
class MockFormLoginAuthenticator extends AbstractFormLoginAuthenticator
{
    private $loginUrl;
    private $defaultSuccessRedirectUrl;

    /**
     * @param mixed $defaultSuccessRedirectUrl
     *
     * @return MockFormLoginAuthenticator
     */
    public function setDefaultSuccessRedirectUrl($defaultSuccessRedirectUrl)
    {
        $this->defaultSuccessRedirectUrl = $defaultSuccessRedirectUrl;

        return $this;
    }

    /**
     * @param mixed $loginUrl
     *
     * @return MockFormLoginAuthenticator
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
