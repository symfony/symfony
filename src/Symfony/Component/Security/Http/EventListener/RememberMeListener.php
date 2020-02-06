<?php

namespace Symfony\Component\Security\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authentication\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class RememberMeListener implements EventSubscriberInterface
{
    private $providerKey;
    private $logger;
    /** @var RememberMeServicesInterface|null */
    private $rememberMeServices;

    public function __construct(string $providerKey, ?LoggerInterface $logger = null)
    {
        $this->providerKey = $providerKey;
        $this->logger = $logger;
    }


    public function setRememberMeServices(RememberMeServicesInterface $rememberMeServices): void
    {
        $this->rememberMeServices = $rememberMeServices;
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        if (!$this->isRememberMeEnabled($event->getAuthenticator(), $event->getProviderKey())) {
            return;
        }

        $this->rememberMeServices->loginSuccess($event->getRequest(), $event->getResponse(), $event->getAuthenticatedToken());
    }

    public function onFailedLogin(LoginFailureEvent $event): void
    {
        if (!$this->isRememberMeEnabled($event->getAuthenticator(), $event->getProviderKey())) {
            return;
        }

        $this->rememberMeServices->loginFail($event->getRequest(), $event->getException());
    }

    private function isRememberMeEnabled(AuthenticatorInterface $authenticator, string $providerKey): bool
    {
        if ($providerKey !== $this->providerKey) {
            // This listener is created for a different firewall.
            return false;
        }

        if (null === $this->rememberMeServices) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: it is not configured for the firewall.', ['authenticator' => \get_class($authenticator)]);
            }

            return false;
        }

        if (!$authenticator->supportsRememberMe()) {
            if (null !== $this->logger) {
                $this->logger->debug('Remember me skipped: your authenticator does not support it.', ['authenticator' => \get_class($authenticator)]);
            }

            return false;
        }

        return true;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onSuccessfulLogin',
            LoginFailureEvent::class => 'onFailedLogin',
        ];
    }
}
