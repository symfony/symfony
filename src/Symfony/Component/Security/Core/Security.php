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
use Symfony\Bundle\SecurityBundle\Security as NewSecurityHelper;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Helper class for commonly-needed security tasks.
 *
 * @deprecated since Symfony 6.2, use \Symfony\Bundle\SecurityBundle\Security instead
 */
class Security implements AuthorizationCheckerInterface
{
    public const ACCESS_DENIED_ERROR = '_security.403_error';
    public const AUTHENTICATION_ERROR = '_security.last_error';
    public const LAST_USERNAME = '_security.last_username';

    /**
     * @deprecated since Symfony 6.2, use \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::MAX_USERNAME_LENGTH instead
     */
    public const MAX_USERNAME_LENGTH = 4096;

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container, bool $triggerDeprecation = true)
    {
        $this->container = $container;

        if ($triggerDeprecation) {
            trigger_deprecation('symfony/security-core', '6.2', 'The "%s" class is deprecated, use "%s" instead.', __CLASS__, NewSecurityHelper::class);
        }
    }

    public function getUser(): ?UserInterface
    {
        if (!$token = $this->getToken()) {
            return null;
        }

        return $token->getUser();
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied subject.
     */
    public function isGranted(mixed $attributes, mixed $subject = null): bool
    {
        return $this->container->get('security.authorization_checker')
            ->isGranted($attributes, $subject);
    }

    public function getToken(): ?TokenInterface
    {
        return $this->container->get('security.token_storage')->getToken();
    }
}
