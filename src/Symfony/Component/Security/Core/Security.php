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

use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Helper class for commonly-needed security tasks.
 */
final class Security
{
    const ACCESS_DENIED_ERROR = '_security.403_error';
    const AUTHENTICATION_ERROR = '_security.last_error';
    const LAST_USERNAME = '_security.last_username';
    const MAX_USERNAME_LENGTH = 4096;

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        if (!$token = $this->getToken()) {
            return null;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        return $user;
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied subject.
     *
     * @param mixed $attributes
     * @param mixed $subject
     *
     * @return bool
     */
    public function isGranted($attributes, $subject = null)
    {
        return $this->container->get('security.authorization_checker')
            ->isGranted($attributes, $subject);
    }

    /**
     * @return TokenInterface|null
     */
    public function getToken()
    {
        return $this->container->get('security.token_storage')->getToken();
    }
}
