<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\Decorator\DecoratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class DecorateControllerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly DecoratorInterface $decorator,
    ) {
    }

    public function decorate(ControllerArgumentsEvent $event): void
    {
        $event->setController($this->decorator->decorate($event->getController()(...)));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => ['decorate', -1024],
        ];
    }
}
