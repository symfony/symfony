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
use Symfony\Component\Webhook\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class HeaderSignatureConfigurator implements RequestConfiguratorInterface
{
    public function __construct(
        private readonly string $algo = 'sha256',
        private readonly string $signatureHeaderName = 'Webhook-Signature',
    ) {
    }

    public function configure(RemoteEvent $event, string $secret, HttpOptions $options): void
    {
        $opts = $options->toArray();
        $headers = $opts['headers'];
        if (!isset($opts['body'])) {
            throw new LogicException('The body must be set.');
        }
        $body = $opts['body'];
        $headers[$this->signatureHeaderName] = $this->algo.'='.hash_hmac($this->algo, $event->getName().$event->getId().$body, $secret);
        $options->setHeaders($headers);
    }
}
