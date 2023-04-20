<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class HandleDelayedMessagesOnKernelTerminateListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoutableMessageBus $routableBus,
        private readonly ReceiverInterface $receiver,
        private readonly string $receiverName,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RateLimiterFactory $rateLimiterFactory = null
    ) {
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $worker = new Worker(
            [$this->receiverName => $this->receiver],
            $this->routableBus,
            $this->eventDispatcher,
            $this->logger,
            [$this->receiverName => $this->rateLimiterFactory],
        );
        $worker->run(['sleep' => 1]);
    }

    public static function getSubscribedEvents(): array
    {
        return [TerminateEvent::class => 'onKernelTerminate', -256];
    }
}
