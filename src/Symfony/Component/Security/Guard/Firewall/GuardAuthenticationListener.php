<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * Authentication listener for the "guard" system.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @final
 */
class GuardAuthenticationListener extends AbstractListener
{
    use GuardAuthenticatorListenerTrait;

    private $guardHandler;
    private $authenticationManager;
    private $providerKey;
    private $guardAuthenticators;
    private $logger;
    private $rememberMeServices;

    /**
     * @param string                            $providerKey         The provider (i.e. firewall) key
     * @param iterable|AuthenticatorInterface[] $guardAuthenticators The authenticators, with keys that match what's passed to GuardAuthenticationProvider
     */
    public function __construct(GuardAuthenticatorHandler $guardHandler, AuthenticationManagerInterface $authenticationManager, string $providerKey, iterable $guardAuthenticators, LoggerInterface $logger = null)
    {
        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->guardHandler = $guardHandler;
        $this->authenticationManager = $authenticationManager;
        $this->providerKey = $providerKey;
        $this->guardAuthenticators = $guardAuthenticators;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        if (null !== $this->logger) {
            $context = ['firewall_key' => $this->providerKey];

            if ($this->guardAuthenticators instanceof \Countable || \is_array($this->guardAuthenticators)) {
                $context['authenticators'] = \count($this->guardAuthenticators);
            }

            $this->logger->debug('Checking for guard authentication credentials.', $context);
        }

        $guardAuthenticators = $this->getSupportingGuardAuthenticators($request);
        if (!$guardAuthenticators) {
            return false;
        }

        $request->attributes->set('_guard_authenticators', $guardAuthenticators);

        return true;
    }

    /**
     * Iterates over each authenticator to see if each wants to authenticate the request.
     */
    public function authenticate(RequestEvent $event)
    {
        $request = $event->getRequest();
        $guardAuthenticators = $request->attributes->get('_guard_authenticators');
        $request->attributes->remove('_guard_authenticators');

        $this->executeGuardAuthenticators($guardAuthenticators, $event);
    }

    /**
     * Should be called if this listener will support remember me.
     */
    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices)
    {
        $this->rememberMeServices = $rememberMeServices;
    }

    protected function getGuardKey(string $key): string
    {
        // get a key that's unique to *this* guard authenticator
        // this MUST be the same as GuardAuthenticationProvider
        return $this->providerKey.'_'.$key;
    }
}
