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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

/**
 * Helper class for commonly-needed security tasks.
 *
 * @final
 */
class Security implements AuthorizationCheckerInterface
{
    public const ACCESS_DENIED_ERROR = '_security.403_error';
    public const AUTHENTICATION_ERROR = '_security.last_error';
    public const LAST_USERNAME = '_security.last_username';
    public const MAX_USERNAME_LENGTH = 4096;

    private $container;

    /**
     * @var array
     */
    private $authenticators;

    public function __construct(array $authenticators, ContainerInterface $container)
    {
        $this->authenticators = $authenticators;
        $this->container = $container;
    }

    public function getUser(): ?UserInterface
    {
        if (!$token = $this->getToken()) {
            return null;
        }

        $user = $token->getUser();
        if (!\is_object($user)) {
            return null;
        }

        if (!$user instanceof UserInterface) {
            return null;
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

    public function autoLogin(UserInterface $user, AuthenticatorInterface $authenticator = null): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if (null === $authenticator) {
            $firewall = $this->container->get('security.firewall.map')->getFirewallConfig($request);

            if (null === $firewall) {
                throw new LogicException('No firewall found as the current route is not covered by any firewall.');
            }
            $firewallName = $firewall->getName();

            if (!\array_key_exists($firewallName, $this->authenticators) || 0 === \count($this->authenticators[$firewallName])) {
                throw new LogicException(sprintf('No authenticators found for the firewall "%s".', $firewallName));
            }
            $firewallAuthenticators = $this->authenticators[$firewallName];

            if (0 === \count($firewallAuthenticators)) {
                throw new LogicException('No authenticator was found for the firewall "%s".');
            }

            if (\count($firewallAuthenticators) > 1) {
                throw new LogicException('Too much authenticators were found for the firewall "%s". You must provide an instance of '.AuthenticatorInterface::class.' to allow an auto login');
            }
            $authenticator = array_pop($firewallAuthenticators);
        }
        // Throw the exception if any pre-auth check does not pass
        $this->container->get('security.user_checker')->checkPreAuth($user);
        $this->container->get('security.user_authenticator')->authenticateUser($user, $authenticator, $request);
    }
}
