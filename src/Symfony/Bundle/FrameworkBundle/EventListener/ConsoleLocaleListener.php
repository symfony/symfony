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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sets the default locale of the intl extension when a command starts, like HTTP requests do.
 *
 * Commands run by another command or during an HTTP request keep the current locale.
 *
 * @internal
 */
final class ConsoleLocaleListener implements EventSubscriberInterface
{
    private ?Command $command = null;

    public function __construct(
        private readonly string $defaultLocale,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 250],
            ConsoleEvents::TERMINATE => ['onTerminate', -1024],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        if ($this->command || $this->requestStack->getMainRequest()) {
            return;
        }

        $this->command = $event->getCommand();

        // Silently ignore if the intl extension is not loaded
        try {
            if (class_exists(\Locale::class, false)) {
                \Locale::setDefault($this->defaultLocale);
            }
        } catch (\Exception) {
        }
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getCommand() === $this->command) {
            $this->command = null;
        }
    }
}
