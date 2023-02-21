<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Core\Security as LegacySecurity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Helper class for commonly-needed security tasks.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Arnaud Frézet <arnaud@larriereguichet.fr>
 *
 * @final
 */
class Security extends LegacySecurity
{
    public const ACCESS_DENIED_ERROR = SecurityRequestAttributes::ACCESS_DENIED_ERROR;
    public const AUTHENTICATION_ERROR = SecurityRequestAttributes::AUTHENTICATION_ERROR;
    public const LAST_USERNAME = SecurityRequestAttributes::LAST_USERNAME;

    public function __construct(private readonly ContainerInterface $container, private readonly array $authenticators = [])
    {
        parent::__construct($container, false);
    }

    public function getFirewallConfig(Request $request): ?FirewallConfig
    {
        return $this->container->get('security.firewall.map')->getFirewallConfig($request);
    }

    /**
     * @param UserInterface $user              The user to authenticate
     * @param string|null   $authenticatorName The authenticator name (e.g. "form_login") or service id (e.g. SomeApiKeyAuthenticator::class) - required only if multiple authenticators are configured
     * @param string|null   $firewallName      The firewall name - required only if multiple firewalls are configured
     */
    public function login(UserInterface $user, string $authenticatorName = null, string $firewallName = null): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $firewallName ??= $this->getFirewallConfig($request)?->getName();

        if (!$firewallName) {
            throw new LogicException('Unable to login as the current route is not covered by any firewall.');
        }

        $authenticator = $this->getAuthenticator($authenticatorName, $firewallName);

        $this->container->get('security.user_checker')->checkPreAuth($user);
        $this->container->get('security.authenticator.managers_locator')->get($firewallName)->authenticateUser($user, $authenticator, $request);
    }

    /**
     * Logout the current user by dispatching the LogoutEvent.
     *
     * @param bool $validateCsrfToken Whether to look for a valid CSRF token based on the `logout` listener configuration
     *
     * @return Response|null The LogoutEvent's Response if any
     *
     * @throws LogoutException When $validateCsrfToken is true and the CSRF token is not found or invalid
     */
    public function logout(bool $validateCsrfToken = true): ?Response
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->container->get('security.token_storage');

        if (!($token = $tokenStorage->getToken()) || !$token->getUser()) {
            throw new LogicException('Unable to logout as there is no logged-in user.');
        }

        $request = $this->container->get('request_stack')->getMainRequest();

        if (!$firewallConfig = $this->container->get('security.firewall.map')->getFirewallConfig($request)) {
            throw new LogicException('Unable to logout as the request is not behind a firewall.');
        }

        if ($validateCsrfToken) {
            if (!$this->container->has('security.csrf.token_manager') || !$logoutConfig = $firewallConfig->getLogout()) {
                throw new LogicException(sprintf('Unable to logout with CSRF token validation. Either make sure that CSRF protection is enabled and "logout" is configured on the "%s" firewall, or bypass CSRF token validation explicitly by passing false to the $validateCsrfToken argument of this method.', $firewallConfig->getName()));
            }
            $csrfToken = ParameterBagUtils::getRequestParameterValue($request, $logoutConfig['csrf_parameter']);
            if (!\is_string($csrfToken) || !$this->container->get('security.csrf.token_manager')->isTokenValid(new CsrfToken($logoutConfig['csrf_token_id'], $csrfToken))) {
                throw new LogoutException('Invalid CSRF token.');
            }
        }

        $logoutEvent = new LogoutEvent($request, $token);
        $this->container->get('security.firewall.event_dispatcher_locator')->get($firewallConfig->getName())->dispatch($logoutEvent);

        $tokenStorage->setToken(null);

        return $logoutEvent->getResponse();
    }

    private function getAuthenticator(?string $authenticatorName, string $firewallName): AuthenticatorInterface
    {
        if (!isset($this->authenticators[$firewallName])) {
            throw new LogicException(sprintf('No authenticators found for firewall "%s".', $firewallName));
        }

        /** @var ServiceProviderInterface $firewallAuthenticatorLocator */
        $firewallAuthenticatorLocator = $this->authenticators[$firewallName];

        if (!$authenticatorName) {
            $authenticatorIds = array_keys($firewallAuthenticatorLocator->getProvidedServices());

            if (!$authenticatorIds) {
                throw new LogicException(sprintf('No authenticator was found for the firewall "%s".', $firewallName));
            }
            if (1 < \count($authenticatorIds)) {
                throw new LogicException(sprintf('Too many authenticators were found for the current firewall "%s". You must provide an instance of "%s" to login programmatically. The available authenticators for the firewall "%s" are "%s".', $firewallName, AuthenticatorInterface::class, $firewallName, implode('" ,"', $authenticatorIds)));
            }

            return $firewallAuthenticatorLocator->get($authenticatorIds[0]);
        }

        if ($firewallAuthenticatorLocator->has($authenticatorName)) {
            return $firewallAuthenticatorLocator->get($authenticatorName);
        }

        $authenticatorId = 'security.authenticator.'.$authenticatorName.'.'.$firewallName;

        if (!$firewallAuthenticatorLocator->has($authenticatorId)) {
            throw new LogicException(sprintf('Unable to find an authenticator named "%s" for the firewall "%s". Available authenticators: "%s".', $authenticatorName, $firewallName, implode('", "', array_keys($firewallAuthenticatorLocator->getProvidedServices()))));
        }

        return $firewallAuthenticatorLocator->get($authenticatorId);
    }
}
