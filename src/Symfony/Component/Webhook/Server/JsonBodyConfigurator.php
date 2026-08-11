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
 */
final class JsonBodyConfigurator implements RequestConfiguratorInterface
{
    private PayloadSerializerInterface $payloadSerializer;

    public function __construct(
        SerializerInterface|PayloadSerializerInterface $payloadSerializer,
        private readonly SignatureFormat $format = SignatureFormat::Legacy,
    ) {
        $this->payloadSerializer = $payloadSerializer instanceof SerializerInterface ? new SerializerPayloadSerializer($payloadSerializer) : $payloadSerializer;
    }

    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
        $payload = $event->getPayload();

        // the Standard Webhooks signature covers the body, so the event name is carried there,
        // under the key the specification reserves for it
        if (SignatureFormat::Legacy !== $this->format) {
            $payload['type'] ??= $event->getName();
        }

        $options->setBody($this->payloadSerializer->serialize($payload));
        $headers = $options->toArray()['headers'] ?? [];
        $headers['Content-Type'] = 'application/json';
        $options->setHeaders($headers);
    }
}
