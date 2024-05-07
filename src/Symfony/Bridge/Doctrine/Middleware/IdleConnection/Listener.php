<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\IdleConnection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class Listener implements EventSubscriberInterface
{
    /**
     * @param \ArrayObject<string, int> $connectionExpiries
     */
    public function __construct(
        private readonly \ArrayObject $connectionExpiries,
        private ContainerInterface $container,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $timestamp = time();

        foreach ($this->connectionExpiries as $name => $expiry) {
            if ($timestamp >= $expiry) {
                // unset before so that we won't retry in case of any failure
                $this->connectionExpiries->offsetUnset($name);

                try {
                    $connection = $this->container->get("doctrine.dbal.{$name}_connection");
                    $connection->close();
                } catch (\Exception) {
                    // ignore exceptions to remain fail-safe
                }
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 192], // before session listeners since they could use the DB
        ];
    }
}
