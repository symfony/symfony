<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Engagespot;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Daniel GORGAN <https://github.com/danut007ro>
 */
final class EngagespotTransport extends AbstractTransport
{
    protected const HOST = 'api.engagespot.co/v3/notifications';

    private $apiKey;
    private $apiSecret;

    public function __construct(string $apiKey, string $apiSecret, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('engagespot://%s?campaign_name=%s', $this->getEndpoint(), $this->campaignName);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['to'])) {
            $options['to'] = $message->getRecipientId();
        }

        $sendToEveryone = $options['everyone'] ?? false;
        if (!$sendToEveryone) {
            // Use either "to" or "identifiers" as recipient list.
            if (null !== $options['to']) {
                $identifiers = [$options['to']];
            } elseif (!\is_array($options['identifiers'] ?? null)) {
                throw new InvalidArgumentException(sprintf('The "%s" transport required the "to" or "identifiers" option to be set when not sending to everyone.', __CLASS__));
            } else {
                $identifiers = $options['identifiers'];
            }
        }

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'X-ENGAGESPOT-API-KEY' => $this->apiKey,
                'X-ENGAGESPOT-API-SECRET' => $this->apiSecret,
            ],
            'json' => [
                'notification' => [
                    'title' => $message->getSubject(),
                    'message' => $message->getContent(),
                    'icon' => $options['icon'] ?? '',
                    'url' => $options['url'] ?? '#',
                ],
                'recipients' => $identifiers ?? null,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            if (202 !== $statusCode) {
                throw new TransportException('Invalid status code received from Engagespot server: '.$statusCode, $response->getContent());
            }
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Engagespot server.', $response, 0, $e);
        }

        return new SentMessage($message, (string) $this);
    }
}
