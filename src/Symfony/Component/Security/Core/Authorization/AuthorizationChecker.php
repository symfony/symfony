<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * AuthorizationChecker is the main authorization point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthorizationChecker implements AuthorizationCheckerInterface
{
    private $tokenStorage;
    private $accessDecisionManager;
    private $authenticationManager;
    private $alwaysAuthenticate;
    private $exceptionOnNoToken;
    /** @var AccessDecision */
    private $lastAccessDecision;

    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, AccessDecisionManagerInterface $accessDecisionManager, bool $alwaysAuthenticate = false, bool $exceptionOnNoToken = true)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->alwaysAuthenticate = $alwaysAuthenticate;
        $this->exceptionOnNoToken = $exceptionOnNoToken;
    }

    /**
     * {@inheritdoc}
     *
     * @throws AuthenticationCredentialsNotFoundException when the token storage has no authentication token and $exceptionOnNoToken is set to true
     */
    final public function isGranted($attribute, $subject = null): bool
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            if ($this->exceptionOnNoToken) {
                throw new AuthenticationCredentialsNotFoundException('The token storage contains no authentication token. One possible reason may be that there is no firewall configured for this URL.');
            }

            $token = new NullToken();
        } else {
            if ($this->alwaysAuthenticate || !$token->isAuthenticated()) {
                $this->tokenStorage->setToken($token = $this->authenticationManager->authenticate($token));
            }
        }

        $this->lastAccessDecision = $this->accessDecisionManager->decide($token, [$attribute], $subject);

        if (\is_bool($this->lastAccessDecision)) {
            trigger_deprecation('symfony/security', 5.1, 'Returning a boolean from the "%s::decide()" method is deprecated. Return an "%s" object instead', \get_class($this->accessDecisionManager), AccessDecision::class);

            return $this->lastAccessDecision;
        }

        return $this->lastAccessDecision->isGranted();
    }

    public function getLastAccessDecision(): AccessDecision
    {
        return $this->lastAccessDecision;
    }
}
