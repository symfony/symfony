<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Acl\Voter\FieldVote;

/**
 * SecurityContext is the main entry point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SecurityContext implements SecurityContextInterface
{
    private $token;
    private $accessDecisionManager;
    private $authenticationManager;
    private $alwaysAuthenticate;

    /**
     * Constructor.
     *
     * @param AuthenticationManagerInterface      $authenticationManager An AuthenticationManager instance
     * @param AccessDecisionManagerInterface|null $accessDecisionManager An AccessDecisionManager instance
     * @param Boolean                             $alwaysAuthenticate
     */
    public function __construct(AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager, $alwaysAuthenticate = false)
    {
        $this->authenticationManager = $authenticationManager;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->alwaysAuthenticate = $alwaysAuthenticate;
    }

    /**
     * Checks if the attributes are granted against the current token.
     *
     * @throws AuthenticationCredentialsNotFoundException when the security context has no authentication token.
     * @param mixed $attributes
     * @param mixed|null $object
     * @return Boolean
     */
    public final function isGranted($attributes, $object = null)
    {
        if (null === $this->token) {
            throw new AuthenticationCredentialsNotFoundException('The security context contains no authentication token.');
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
