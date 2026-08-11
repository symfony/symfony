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

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HeadersConfigurator implements RequestConfiguratorInterface
{
    public function __construct(
        private readonly string $eventHeaderName = 'Webhook-Event',
        private readonly string $idHeaderName = 'Webhook-Id',
        private readonly string $timestampHeaderName = 'Webhook-Timestamp',
        private readonly ?ClockInterface $clock = null,
        private readonly SignatureFormat $format = SignatureFormat::Legacy,
    ) {
    }

    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
        $headers = [
            $this->idHeaderName => $event->getId(),
            $this->timestampHeaderName => (string) ($this->clock?->now()->getTimestamp() ?? time()),
        ];

        // the Standard Webhooks signature covers no header, so sending the event name as one would
        // offer the receiver a value it cannot trust; JsonBodyConfigurator puts it in the payload
        if (SignatureFormat::Standard !== $this->format) {
            $headers[$this->eventHeaderName] = $event->getName();
        }

        $options->setHeaders($headers);
    }
}
