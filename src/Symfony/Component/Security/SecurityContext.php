<?php

namespace Symfony\Component\Security;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Authorization\AccessDecisionManager;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * SecurityContext is the main entry point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityContext
{
    const ACCESS_DENIED_ERROR  = '_security.403_error';
    const AUTHENTICATION_ERROR = '_security.last_error';
    const LAST_USERNAME        = '_security.last_username';

    protected $token;
    protected $accessDecisionManager;

    /**
     * Constructor.
     *
     * @param AccessDecisionManager|null $accessDecisionManager An AccessDecisionManager instance
     */
    public function __construct(AccessDecisionManager $accessDecisionManager = null)
    {
        $this->accessDecisionManager = $accessDecisionManager;
    }

    public function getUser()
    {
        return null === $this->token ? null : $this->token->getUser();
    }

    public function vote($attributes, $object = null)
    {
        if (null === $this->token || null === $this->accessDecisionManager) {
            return false;
        }

        return $this->accessDecisionManager->decide($this->token, (array) $attributes, $object);
    }

    public function isAuthenticated()
    {
        return null === $this->token ? false : $this->token->isAuthenticated();
    }

    /**
     * Gets the currently authenticated token.
     *
     * @return TokenInterface|null A TokenInterface instance or null if no authentication information is available
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Sets the currently authenticated token.
     *
     * @param TokenInterface $token A TokenInterface token, or null if no further authentication information should be stored
     */
    public function setToken(TokenInterface $token = null)
    {
        $this->token = $token;
    }
}
