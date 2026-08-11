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
use Symfony\Component\Webhook\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\LogicException;
use Symfony\Component\Webhook\StandardWebhooksSignature;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class HeaderSignatureConfigurator implements RequestConfiguratorInterface
{
    public function __construct(
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
        private readonly SignatureFormat $format = SignatureFormat::Legacy,
        private readonly string $timestampHeaderName = 'Webhook-Timestamp',
    ) {
    }

    public function configure(RemoteEvent $event, #[\SensitiveParameter] string $secret, HttpOptions $options): void
    {
        if (!$secret) {
            throw new InvalidArgumentException('A non-empty secret is required.');
        }

        $opts = $options->toArray();
        $headers = $opts['headers'] ?? [];
        if (!isset($opts['body'])) {
            throw new LogicException('The body must be set.');
        }
        $body = $opts['body'];

        $signatures = [];
        if (SignatureFormat::Standard !== $this->format) {
            $signatures[] = $this->algo.'='.hash_hmac($this->algo, $event->getName().$event->getId().$body, $secret);
        }
        if (SignatureFormat::Legacy !== $this->format) {
            if (!isset($headers[$this->timestampHeaderName])) {
                throw new LogicException(\sprintf('The "%s" header must be set when emitting a Standard Webhooks signature; ensure "HeadersConfigurator" runs before this configurator.', $this->timestampHeaderName));
            }

            $signatures[] = StandardWebhooksSignature::sign($event->getId(), $headers[$this->timestampHeaderName], $body, $secret);
        }

        $headers[$this->signatureHeaderName] = implode(' ', $signatures);
        $options->setHeaders($headers);
    }
}
