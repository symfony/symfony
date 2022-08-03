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
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * Decorates the AuthenticatorManagerListener to collect information about security authenticators.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableAuthenticatorManagerListener extends AbstractListener
{
    private AuthenticatorManagerListener $authenticationManagerListener;
    private array $authenticatorsInfo = [];
    private bool $hasVardumper;

    public function __construct(AuthenticatorManagerListener $authenticationManagerListener)
    {
        $this->authenticationManagerListener = $authenticationManagerListener;
        $this->hasVardumper = class_exists(ClassStub::class);
    }

    public function supports(Request $request): ?bool
    {
        return $this->authenticationManagerListener->supports($request);
    }

    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$authenticators = $request->attributes->get('_security_authenticators')) {
            return;
        }

        foreach ($request->attributes->get('_security_skipped_authenticators') as $skippedAuthenticator) {
            $this->authenticatorsInfo[] = [
                'supports' => false,
                'stub' => $this->hasVardumper ? new ClassStub(\get_class($skippedAuthenticator)) : \get_class($skippedAuthenticator),
                'passport' => null,
                'duration' => 0,
            ];
        }

        foreach ($authenticators as $key => $authenticator) {
            $authenticators[$key] = new TraceableAuthenticator($authenticator);
        }

        $request->attributes->set('_security_authenticators', $authenticators);

        $this->authenticationManagerListener->authenticate($event);

        foreach ($authenticators as $authenticator) {
            $this->authenticatorsInfo[] = $authenticator->getInfo();
        }
    }

    public function getAuthenticatorManagerListener(): AuthenticatorManagerListener
    {
        return $this->authenticationManagerListener;
    }

    public function getAuthenticatorsInfo(): array
    {
        return $this->authenticatorsInfo;
    }
}
