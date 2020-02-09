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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticatorHandler;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PreAuthenticationToken;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @experimental in 5.1
 */
class AuthenticatorManagerListener extends AbstractListener
{
    private $authenticationManager;
    private $authenticatorHandler;
    private $authenticators;
    protected $providerKey;
    private $eventDispatcher;
    protected $logger;

    /**
     * @param AuthenticatorInterface[] $authenticators
     */
    public function __construct(AuthenticationManagerInterface $authenticationManager, AuthenticatorHandler $authenticatorHandler, iterable $authenticators, string $providerKey, EventDispatcherInterface $eventDispatcher, ?LoggerInterface $logger = null)
    {
        $this->authenticationManager = $authenticationManager;
        $this->authenticatorHandler = $authenticatorHandler;
        $this->authenticators = $authenticators;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function supports(Request $request): ?bool
    {
        if (null !== $this->logger) {
            $context = ['firewall_key' => $this->providerKey];

            if ($this->authenticators instanceof \Countable || \is_array($this->authenticators)) {
                $context['authenticators'] = \count($this->authenticators);
            }

            $this->logger->debug('Checking for guard authentication credentials.', $context);
        }

        [$authenticators, $lazy] = $this->getSupportingAuthenticators($request);
        if (!$authenticators) {
            return false;
        }

        $request->attributes->set('_guard_authenticators', $authenticators);

        return $lazy ? null : true;
    }

    public function authenticate(RequestEvent $event)
    {
        $request = $event->getRequest();
        $authenticators = $request->attributes->get('_guard_authenticators');
        $request->attributes->remove('_guard_authenticators');
        if (!$authenticators) {
            return;
        }

        $this->executeAuthenticators($authenticators, $event);
    }

    protected function getSupportingAuthenticators(Request $request): array
    {
        $authenticators = [];
        $lazy = true;
        foreach ($this->authenticators as $key => $authenticator) {
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            if (false !== $supports = $authenticator->supports($request)) {
                $authenticators[$key] = $authenticator;
                $lazy = $lazy && null === $supports;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }
        }

        return [$authenticators, $lazy];
    }

    /**
     * @param AuthenticatorInterface[] $authenticators
     */
    protected function executeAuthenticators(array $authenticators, RequestEvent $event): void
    {
        foreach ($authenticators as $key => $authenticator) {
            // recheck if the authenticator still supports the listener. support() is called
            // eagerly (before token storage is initialized), whereas authenticate() is called
            // lazily (after initialization). This is important for e.g. the AnonymousAuthenticator
            // as its support is relying on the (initialized) token in the TokenStorage.
            if (false === $authenticator->supports($event->getRequest())) {
                $this->logger->debug('Skipping the "{authenticator}" authenticator as it did not support the request.', ['authenticator' => \get_class($authenticator)]);
                continue;
            }

            $this->executeAuthenticator($key, $authenticator, $event);

            if ($event->hasResponse()) {
                if (null !== $this->logger) {
                    $this->logger->debug('The "{authenticator}" authenticator set the response. Any later authenticator will not be called', ['authenticator' => \get_class($authenticator)]);
                }

                break;
            }
        }
    }

    private function executeAuthenticator(string $uniqueAuthenticatorKey, AuthenticatorInterface $authenticator, RequestEvent $event): void
    {
        $request = $event->getRequest();
        try {
            if (null !== $this->logger) {
                $this->logger->debug('Calling getCredentials() on authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }

            // allow the authenticator to fetch authentication info from the request
            $credentials = $authenticator->getCredentials($request);

            if (null === $credentials) {
                throw new \UnexpectedValueException(sprintf('The return value of "%1$s::getCredentials()" must not be null. Return false from "%1$s::supports()" instead.', \get_class($authenticator)));
            }

            // create a token with the unique key, so that the provider knows which authenticator to use
            $token = new PreAuthenticationToken($credentials, $uniqueAuthenticatorKey, $uniqueAuthenticatorKey);

            if (null !== $this->logger) {
                $this->logger->debug('Passing token information to the AuthenticatorManager', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($authenticator)]);
            }
            // pass the token into the AuthenticationManager system
            // this indirectly calls AuthenticatorManager::authenticate()
            $token = $this->authenticationManager->authenticate($token);

            if (null !== $this->logger) {
                $this->logger->info('Authenticator successful!', ['token' => $token, 'authenticator' => \get_class($authenticator)]);
            }

            // sets the token on the token storage, etc
            $this->authenticatorHandler->authenticateWithToken($token, $request, $this->providerKey);
        } catch (AuthenticationException $e) {
            // oh no! Authentication failed!

            if (null !== $this->logger) {
                $this->logger->info('Authenticator failed.', ['exception' => $e, 'authenticator' => \get_class($authenticator)]);
            }

            $response = $this->authenticatorHandler->handleAuthenticationFailure($e, $request, $authenticator, $this->providerKey);

            if ($response instanceof Response) {
                $event->setResponse($response);
            }

            $this->eventDispatcher->dispatch(new LoginFailureEvent($e, $authenticator, $request, $response, $this->providerKey));

            return;
        }

        // success!
        $response = $this->authenticatorHandler->handleAuthenticationSuccess($token, $request, $authenticator, $this->providerKey);
        if ($response instanceof Response) {
            if (null !== $this->logger) {
                $this->logger->debug('Authenticator set success response.', ['response' => $response, 'authenticator' => \get_class($authenticator)]);
            }

            $event->setResponse($response);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Authenticator set no success response: request continues.', ['authenticator' => \get_class($authenticator)]);
            }
        }

        $this->eventDispatcher->dispatch(new LoginSuccessEvent($authenticator, $token, $request, $response, $this->providerKey));
    }
}
