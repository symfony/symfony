<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorates the AuthenticatorManagerListener to collect information about security authenticators.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableAuthenticatorManagerListener extends AbstractListener implements ResetInterface
{
    private array $authenticators = [];

    public function __construct(private AuthenticatorManagerListener $authenticationManagerListener)
    {
    }

    public function supports(Request $request): ?bool
    {
        $supports = $this->authenticationManagerListener->supports($request);

        foreach ($request->attributes->get('_security_skipped_authenticators') as $authenticator) {
            $this->authenticators[] = $authenticator instanceof TraceableAuthenticator
                ? $authenticator
                : new TraceableAuthenticator($authenticator)
            ;
        }

        $supportedAuthenticators = [];
        foreach ($request->attributes->get('_security_authenticators') as $authenticator) {
            $this->authenticators[] = $supportedAuthenticators[] = $authenticator instanceof TraceableAuthenticator
                ? $authenticator :
                new TraceableAuthenticator($authenticator)
            ;
        }
        $request->attributes->set('_security_authenticators', $supportedAuthenticators);

        return $supports;
    }

    public function authenticate(RequestEvent $event): void
    {
        $this->authenticationManagerListener->authenticate($event);
    }

    public function getAuthenticatorManagerListener(): AuthenticatorManagerListener
    {
        return $this->authenticationManagerListener;
    }

    public function getAuthenticatorsInfo(): array
    {
        return array_map(
            static fn (TraceableAuthenticator $authenticator) => $authenticator->getInfo(),
            $this->authenticators
        );
    }

    public function reset(): void
    {
        $this->authenticators = [];
    }
}
