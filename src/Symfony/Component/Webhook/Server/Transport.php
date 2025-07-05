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
 */
class Transport implements TransportInterface
{
    private array $configurators = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        ...$params
    ) {
        if (1 === count($params) && $params[0] instanceof \Traversable) {
            $this->configurators = iterator_to_array($params[0]);
        } elseif (3 === count($params)) {
            trigger_deprecation('symfony/webhook', '7.3', 'Individual configurators for webhook transport is deprecated, use an iterable instead.');

            $this->configurators = [
                $params[0],
                $params[1],
                $params[2],
            ];
        } else {
            throw new \InvalidArgumentException(sprintf('Expected a single Traversable argument or three configurators, got %d arguments.', count($params)));
        }
    }

    public function send(Subscriber $subscriber, RemoteEvent $event): void
    {
        $options = new HttpOptions();
        $secret = $subscriber->getSecret();

        foreach ($this->configurators as $configurator) {
            $configurator->configure($event, $secret, $options);
        }

        $this->client->request('POST', $subscriber->getUrl(), $options->toArray());
    }
}
