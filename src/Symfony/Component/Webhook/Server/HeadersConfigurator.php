<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Server;

use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class HeadersConfigurator implements RequestConfiguratorInterface
{
    public function __construct(
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
    ) {
    }

    public function configure(RemoteEvent $event, string $secret, HttpOptions $options): void
    {
        $options->setHeaders([
            $this->eventHeaderName => $event->getName(),
            $this->idHeaderName => $event->getId(),
        ]);
    }
}
