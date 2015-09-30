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

@trigger_error('The '.__NAMESPACE__.'\SecurityContext class is deprecated since version 2.6 and will be removed in 3.0. Use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage or Symfony\Component\Security\Core\Authorization\AuthorizationChecker instead.', E_USER_DEPRECATED);

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * SecurityContext is the main entry point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since version 2.6, to be removed in 3.0.
 */
class SecurityContext implements SecurityContextInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * For backwards compatibility, the signature of sf <2.6 still works.
     *
     * @param TokenStorageInterface|AuthenticationManagerInterface         $tokenStorage
     * @param AuthorizationCheckerInterface|AccessDecisionManagerInterface $authorizationChecker
     * @param bool                                                         $alwaysAuthenticate   only applicable with old signature
     */
    public function __construct($tokenStorage, $authorizationChecker, $alwaysAuthenticate = false)
    {
        $oldSignature = $tokenStorage instanceof AuthenticationManagerInterface && $authorizationChecker instanceof AccessDecisionManagerInterface;
        $newSignature = $tokenStorage instanceof TokenStorageInterface && $authorizationChecker instanceof AuthorizationCheckerInterface;

        // confirm possible signatures
        if (!$oldSignature && !$newSignature) {
            throw new \BadMethodCallException('Unable to construct SecurityContext, please provide the correct arguments');
        }

        if ($oldSignature) {
            // renamed for clarity
            $authenticationManager = $tokenStorage;
            $accessDecisionManager = $authorizationChecker;
            $tokenStorage = new TokenStorage();
            $authorizationChecker = new AuthorizationChecker($tokenStorage, $authenticationManager, $accessDecisionManager, $alwaysAuthenticate);
        }

        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @deprecated since version 2.6, to be removed in 3.0. Use TokenStorageInterface::getToken() instead.
     *
     * {@inheritdoc}
     */
    public function getToken()
    {
        return $this->tokenStorage->getToken();
    }

    /**
     * @deprecated since version 2.6, to be removed in 3.0. Use TokenStorageInterface::setToken() instead.
     *
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null)
    {
        return $this->tokenStorage->setToken($token);
    }

    /**
     * @deprecated since version 2.6, to be removed in 3.0. Use AuthorizationCheckerInterface::isGranted() instead.
     *
     * {@inheritdoc}
     */
    public function isGranted($attributes, $object = null)
    {
        return $this->authorizationChecker->isGranted($attributes, $object);
    }
}
