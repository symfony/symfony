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
use Symfony\Component\Webhook\Subscriber;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class Transport implements TransportInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly RequestConfiguratorInterface $headers,
        private readonly RequestConfiguratorInterface $body,
        private readonly RequestConfiguratorInterface $signer,
    ) {
    }

    public function send(Subscriber $subscriber, RemoteEvent $event): void
    {
        $options = new HttpOptions();

        $this->headers->configure($event, $subscriber->getSecret(), $options);
        $this->body->configure($event, $subscriber->getSecret(), $options);
        $this->signer->configure($event, $subscriber->getSecret(), $options);

        $this->client->request('POST', $subscriber->getUrl(), $options->toArray());
    }
}
