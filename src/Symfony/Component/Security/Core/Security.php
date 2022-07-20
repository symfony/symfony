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
 *
 * @final
 */
class Security
{
    public const ACCESS_DENIED_ERROR = '_security.403_error';
    public const AUTHENTICATION_ERROR = '_security.last_error';
    public const LAST_USERNAME = '_security.last_username';
    public const MAX_USERNAME_LENGTH = 4096;

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
        if (!\is_object($user)) {
            return null;
        }

        if (!$user instanceof UserInterface) {
            @trigger_error(sprintf('Accessing the user object "%s" that is not an instance of "%s" from "%s()" is deprecated since Symfony 4.2, use "getToken()->getUser()" instead.', \get_class($user), UserInterface::class, __METHOD__), \E_USER_DEPRECATED);
            // return null; // 5.0 behavior
        }

        return $user;
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied subject.
     *
     * @param mixed $attributes
     * @param mixed $subject
     */
    public function isGranted($attributes, $subject = null): bool
    {
        return $this->container->get('security.authorization_checker')
            ->isGranted($attributes, $subject);
    }

    public function getToken(): ?TokenInterface
    {
        return $this->container->get('security.token_storage')->getToken();
    }
}
