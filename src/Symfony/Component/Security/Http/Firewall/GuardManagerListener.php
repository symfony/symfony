<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @experimental in 5.1
 */
class GuardManagerListener
{
    use GuardManagerListenerTrait;

    private $authenticationManager;
    private $guardHandler;
    private $guardAuthenticators;
    protected $providerKey;
    protected $logger;
    private $rememberMeServices;

    /**
     * @param AuthenticatorInterface[] $guardAuthenticators
     */
    public function __construct(AuthenticationManagerInterface $authenticationManager, GuardAuthenticatorHandler $guardHandler, iterable $guardAuthenticators, string $providerKey, ?LoggerInterface $logger = null)
    {
        $this->authenticationManager = $authenticationManager;
        $this->guardHandler = $guardHandler;
        $this->guardAuthenticators = $guardAuthenticators;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
    }

    public function __invoke(RequestEvent $requestEvent)
    {
        $request = $requestEvent->getRequest();
        $guardAuthenticators = $this->getSupportingGuardAuthenticators($request);
        if (!$guardAuthenticators) {
            return;
        }

        $this->executeGuardAuthenticators($guardAuthenticators, $requestEvent);
    }

    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices = $rememberMeServices;
    }

    protected function createPreAuthenticatedToken($credentials, string $uniqueGuardKey, string $providerKey): PreAuthenticationGuardToken
    {
        return new PreAuthenticationGuardToken($credentials, $uniqueGuardKey, $providerKey);
    }

    protected function getGuardKey(string $key): string
    {
        // Guard authenticators in the GuardManagerListener are already indexed
        // by an unique key
        return $key;
    }
}
