<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\UserSession;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class UserSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UserSessionRepositoryInterface $userSessionRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'clearSession',
            LoginSuccessEvent::class => 'onLogin',
            RequestEvent::class => 'onRequest',
        ];
    }

    public function clearSession(LogoutEvent $event): void
    {
        $userSessionId = $event->getRequest()
            ->getSession()
            ->getId();
        $userSession = $this->userSessionRepository->findOneById($userSessionId);
        if (null !== $userSession) {
            $this->userSessionRepository->remove($userSession);
        }
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            $userSessionId = $event->getRequest()
                ->getSession()
                ->getId();
            $this->associatedSessionToUser($userSessionId, $user);
        }
    }

    public function onLogin(LoginSuccessEvent $event): void
    {
        $this->associatedSessionToUser(
            $event->getRequest()->getSession()->getId(),
            $event->getUser())
        ;
    }

    private function associatedSessionToUser(string $userSessionId, UserInterface $user): void
    {
        $userSession = $this->userSessionRepository->findOneById($userSessionId);
        if (null === $userSession || null !== $userSession->getUser()) {
            return;
        }
        $userSession->setUser($user);
        $this->userSessionRepository->save($userSession);
    }
}
