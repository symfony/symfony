<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Security;

use Symfony\Bundle\FrameworkBundle\Exception\LogicException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * A collection of utility methods to ease the integration of SecurityBundle services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait SecurityHelperTrait
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var CsrfTokenManagerInterface
     */
    protected $csrfTokenManager;

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     *
     * @param mixed $attributes The attributes
     * @param mixed $object     The object
     *
     * @return bool
     */
    protected function isGranted($attributes, $object = null)
    {
        if ($this->authorizationChecker === null) {
            if (!isset($this->container)) {
                throw new LogicException(
                    'Unable to load the authorization checker. Please set the $authorizationChecker property or make '
                    .__CLASS__.' container-aware.'
                );
            }
            if (!$this->container->has('security.authorization_checker')) {
                throw new LogicException('The SecurityBundle is not registered in your application.');
            }

            $this->authorizationChecker = $this->container->get('security.authorization_checker');
        }

        return $this->authorizationChecker->isGranted($attributes, $object);
    }

    /**
     * Throws an exception unless the attributes are granted against the current authentication token and optionally
     * supplied object.
     *
     * @param mixed  $attributes The attributes
     * @param mixed  $object     The object
     * @param string $message    The message passed to the exception
     *
     * @throws AccessDeniedException
     */
    protected function denyAccessUnlessGranted($attributes, $object = null, $message = 'Access Denied.')
    {
        if (!$this->isGranted($attributes, $object)) {
            throw $this->createAccessDeniedException($message);
        }
    }

    /**
     * Returns an AccessDeniedException.
     *
     * This will result in a 403 response code. Usage example:
     *
     *     throw $this->createAccessDeniedException('Unable to access this page!');
     *
     * @param string          $message  A message
     * @param \Exception|null $previous The previous exception
     *
     * @return AccessDeniedException
     */
    protected function createAccessDeniedException($message = 'Access Denied.', \Exception $previous = null)
    {
        return new AccessDeniedException($message, $previous);
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @return mixed
     *
     * @see TokenInterface::getUser()
     */
    protected function getUser()
    {
        if ($this->tokenStorage === null) {
            if (!isset($this->container)) {
                throw new LogicException(
                    'Unable to load the token storage. Please set the $tokenStorage property or make '
                        .__CLASS__.' container-aware.'
                );
            }
            if (!$this->container->has('security.token_storage')) {
                throw new LogicException('The SecurityBundle is not registered in your application.');
            }

            $this->tokenStorage = $this->container->get('security.token_storage');
        }

        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    /**
     * Checks the validity of a CSRF token.
     *
     * @param string $id    The id used when generating the token
     * @param string $token The actual token sent with the request that should be validated
     *
     * @return bool
     */
    protected function isCsrfTokenValid($id, $token)
    {
        if ($this->csrfTokenManager === null) {
            if (!isset($this->container)) {
                throw new LogicException(
                    'Unable to load the CSRF token manager. Please set the $csrfTokenManager property or make '
                    .__CLASS__.' container-aware.'
                );
            }
            if (!$this->container->has('security.csrf.token_manager')) {
                throw new LogicException('CSRF protection is not enabled in your application.');
            }

            $this->csrfTokenManager = $this->container->get('security.csrf.token_manager');
        }

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }
}
