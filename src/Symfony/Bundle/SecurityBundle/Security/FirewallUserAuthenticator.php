<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Security;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Martin Kirilov <wucdbm@gmail.com>
 *
 * @final
 * @experimental in 5.2
 */
class FirewallUserAuthenticator
{
    private $firewallLocator;
    private $eventDispatcher;

    public function __construct(ContainerInterface $firewallLocator, EventDispatcherInterface $eventDispatcher)
    {
        $this->firewallLocator = $firewallLocator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param BadgeInterface[] $badges Optionally, pass some Passport badges to use for the manual login
     */
    public function authenticateUser(UserInterface $user, Request $request, string $firewallName, array $badges = []): void
    {
        // TODO Tests
        // TODO Throw if not master request?
        if (!$request->hasSession()) {
            return;
        }

        if (!$this->firewallLocator->has('security.firewall.map.context.'.$firewallName)) {
            throw new \LogicException(sprintf('Firewall "%s" not found. Did you register your firewall?', $firewallName));
        }

        /** @var FirewallContext $firewallContext */
        $firewallContext = $this->firewallLocator->get('security.firewall.map.context.'.$firewallName);
        // todo can firewall config ever be null?
        $firewallConfig = $firewallContext->getConfig();

        // Note: We're only using PreAuthenticatedAuthenticator because of Symfony\Component\Security\Http\EventListener\RememberMeListener
        $authenticator = new PreAuthenticatedAuthenticator();
        // create PreAuthenticatedToken token for the User
        $token = $authenticator->createAuthenticatedToken($passport = new SelfValidatingPassport(new UserBadge($user->getUsername(), function () use ($user) { return $user; }), $badges), $firewallName);

        // announce the authenticated token
        $token = $this->eventDispatcher->dispatch(new AuthenticationTokenCreatedEvent($token))->getAuthenticatedToken();

        $this->eventDispatcher->dispatch($loginSuccessEvent = new LoginSuccessEvent($authenticator, $passport, $token, $request, null, $firewallName));

        $sessionKey = '_security_'.$firewallConfig->getContext();
        $session = $request->getSession();
        // increments the internal session usage index
        $session->getMetadataBag();
        $session->set($sessionKey, serialize($token));
    }
}
