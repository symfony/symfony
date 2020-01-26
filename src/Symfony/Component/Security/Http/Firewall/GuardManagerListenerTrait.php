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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface as CoreAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticationGuardToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\AuthenticatorInterface;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 * @author Amaury Leroux de Lens <amaury@lerouxdelens.com>
 *
 * @internal
 */
trait GuardManagerListenerTrait
{
    protected function getSupportingGuardAuthenticators(Request $request): array
    {
        $guardAuthenticators = [];
        foreach ($this->guardAuthenticators as $key => $guardAuthenticator) {
            if (null !== $this->logger) {
                $this->logger->debug('Checking support on guard authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            if ($guardAuthenticator->supports($request)) {
                $guardAuthenticators[$key] = $guardAuthenticator;
            } elseif (null !== $this->logger) {
                $this->logger->debug('Guard authenticator does not support the request.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }
        }

        return $guardAuthenticators;
    }

    /**
     * @param (CoreAuthenticatorInterface|AuthenticatorInterface)[] $guardAuthenticators
     */
    protected function executeGuardAuthenticators(array $guardAuthenticators, RequestEvent $event): void
    {
        foreach ($guardAuthenticators as $key => $guardAuthenticator) {
            $uniqueGuardKey = $this->getGuardKey($key);

            $this->executeGuardAuthenticator($uniqueGuardKey, $guardAuthenticator, $event);

            if ($event->hasResponse()) {
                if (null !== $this->logger) {
                    $this->logger->debug('The "{authenticator}" authenticator set the response. Any later authenticator will not be called', ['authenticator' => \get_class($guardAuthenticator)]);
                }

                break;
            }
        }
    }

    /**
     * @param CoreAuthenticatorInterface|AuthenticatorInterface $guardAuthenticator
     */
    private function executeGuardAuthenticator(string $uniqueGuardKey, $guardAuthenticator, RequestEvent $event)
    {
        if (!$guardAuthenticator instanceof AuthenticatorInterface && !$guardAuthenticator instanceof CoreAuthenticatorInterface) {
            throw new \UnexpectedValueException('Invalid guard authenticator passed to '.__METHOD__.'. Expected AuthenticatorInterface of either Security Core or Security Guard.');
        }

        $request = $event->getRequest();
        try {
            if (null !== $this->logger) {
                $this->logger->debug('Calling getCredentials() on guard authenticator.', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            // allow the authenticator to fetch authentication info from the request
            $credentials = $guardAuthenticator->getCredentials($request);

            if (null === $credentials) {
                throw new \UnexpectedValueException(sprintf('The return value of "%1$s::getCredentials()" must not be null. Return false from "%1$s::supports()" instead.', get_debug_type($guardAuthenticator)));
            }

            // create a token with the unique key, so that the provider knows which authenticator to use
            $token = $this->createPreAuthenticatedToken($credentials, $uniqueGuardKey, $this->providerKey);

            if (null !== $this->logger) {
                $this->logger->debug('Passing guard token information to the GuardAuthenticationProvider', ['firewall_key' => $this->providerKey, 'authenticator' => \get_class($guardAuthenticator)]);
            }
            // pass the token into the AuthenticationManager system
            // this indirectly calls GuardAuthenticationProvider::authenticate()
            $token = $this->authenticationManager->authenticate($token);

            if (null !== $this->logger) {
                $this->logger->info('Guard authentication successful!', ['token' => $token, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            // sets the token on the token storage, etc
            $this->guardHandler->authenticateWithToken($token, $request, $this->providerKey);
        } catch (AuthenticationException $e) {
            // oh no! Authentication failed!

            if (null !== $this->logger) {
                $this->logger->info('Guard authentication failed.', ['exception' => $e, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            $response = $this->guardHandler->handleAuthenticationFailure($e, $request, $guardAuthenticator, $this->providerKey);

            if ($response instanceof Response) {
                $event->setResponse($response);
            }

            return;
        }

        // success!
        $response = $this->guardHandler->handleAuthenticationSuccess($token, $request, $guardAuthenticator, $this->providerKey);
        if ($response instanceof Response) {
            if (null !== $this->logger) {
                $this->logger->debug('Guard authenticator set success response.', ['response' => $response, 'authenticator' => \get_class($guardAuthenticator)]);
            }

            $event->setResponse($response);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Guard authenticator set no success response: request continues.', ['authenticator' => \get_class($guardAuthenticator)]);
            }
        }

        // attempt to trigger the remember me functionality
        $this->triggerRememberMe($guardAuthenticator, $request, $token, $response);
    }

    /**
     * Checks to see if remember me is supported in the authenticator and
     * on the firewall. If it is, the RememberMeServicesInterface is notified.
     *
     * @param CoreAuthenticatorInterface|AuthenticatorInterface $guardAuthenticator
     */
    private function triggerRememberMe($guardAuthenticator, Request $request, TokenInterface $token, Response $response = null)
    {
        if (!$guardAuthenticator instanceof AuthenticatorInterface && !$guardAuthenticator instanceof CoreAuthenticatorInterface) {
            throw new \UnexpectedValueException('Invalid guard authenticator passed to '.__METHOD__.'. Expected AuthenticatorInterface of either Security Core or Security Guard.');
        }

        if (null === $this->rememberMeServices) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: it is not configured for the firewall.', ['authenticator' => \get_class($guardAuthenticator)]);
            }

            return;
        }

        if (!$guardAuthenticator->supportsRememberMe()) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: your authenticator does not support it.', ['authenticator' => \get_class($guardAuthenticator)]);
            }

            return;
        }

        if (!$response instanceof Response) {
            throw new \LogicException(sprintf('"%s::onAuthenticationSuccess()" *must* return a Response if you want to use the remember me functionality. Return a Response, or set remember_me to false under the guard configuration.', get_debug_type($guardAuthenticator)));
        }

        $this->rememberMeServices->loginSuccess($request, $response, $token);
    }

    abstract protected function getGuardKey(string $key): string;

    abstract protected function createPreAuthenticatedToken($credentials, string $uniqueGuardKey, string $providerKey): PreAuthenticationGuardToken;
}
