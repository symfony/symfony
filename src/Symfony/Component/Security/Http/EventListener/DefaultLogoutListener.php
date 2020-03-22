<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Default logout listener will redirect users to a configured path.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 *
 * @final
 */
class DefaultLogoutListener implements EventSubscriberInterface
{
    private $httpUtils;
    private $targetUrl;

    public function __construct(HttpUtils $httpUtils, string $targetUrl = '/')
    {
        $this->httpUtils = $httpUtils;
        $this->targetUrl = $targetUrl;
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (null !== $event->getResponse()) {
            return;
        }

        $event->setResponse($this->httpUtils->createRedirectResponse($event->getRequest(), $this->targetUrl));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => ['onLogout', 64],
        ];
    }
}
