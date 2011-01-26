<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Acl\Voter\FieldVote;

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
    protected $authenticationManager;
    protected $alwaysAuthenticate;

    /**
     * Constructor.
     *
     * @param AccessDecisionManagerInterface|null $accessDecisionManager An AccessDecisionManager instance
     */
    public function __construct(AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager = null, $alwaysAuthenticate = false)
    {
        $this->authenticationManager = $authenticationManager;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->alwaysAuthenticate = $alwaysAuthenticate;
    }

    public function getUser()
    {
        return null === $this->token ? null : $this->token->getUser();
    }

    public function vote($attributes, $object = null, $field = null)
    {
        if (null === $this->token || null === $this->accessDecisionManager) {
            return false;
        }

        if ($field !== null) {
            if (null === $object) {
                throw new \InvalidArgumentException('$object cannot be null when field is not null.');
            }

            $object = new FieldVote($object, $field);
        }

        if ($this->alwaysAuthenticate || !$this->token->isAuthenticated()) {
            $this->token = $this->authenticationManager->authenticate($this->token);
        }

        return $this->accessDecisionManager->decide($this->token, (array) $attributes, $object);
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
