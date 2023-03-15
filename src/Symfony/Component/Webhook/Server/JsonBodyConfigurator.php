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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class JsonBodyConfigurator implements RequestConfiguratorInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function configure(RemoteEvent $event, string $secret, HttpOptions $options): void
    {
        $body = $this->serializer->serialize($event->getPayload(), 'json');
        $options->setBody($body);
        $headers = $options->toArray()['headers'];
        $headers['Content-Type'] = 'application/json';
        $options->setHeaders($headers);
    }
}
