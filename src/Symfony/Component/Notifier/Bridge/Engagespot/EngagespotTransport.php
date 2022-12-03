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
    protected const HOST = 'api.engagespot.co/2/campaigns';

    private $apiKey;
    private $campaignName;

    public function __construct(#[\SensitiveParameter] string $apiKey, string $campaignName, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->campaignName = $campaignName;
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
                'API-Key' => $this->apiKey,
            ],
            'json' => [
                'campaign_name' => $options['campaign_name'] ?? $this->campaignName,
                'notification' => [
                    'title' => $message->getSubject(),
                    'message' => $message->getContent(),
                    'icon' => $options['icon'] ?? '',
                    'url' => $options['url'] ?? '#',
                ],
                'send_to' => $sendToEveryone ? 'everyone' : 'identifiers',
                'identifiers' => $identifiers ?? null,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new TransportException('Invalid status code received from Engagespot server: '.$statusCode, $response);
            }
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Engagespot server.', $response, 0, $e);
        }

        $jsonContents = $response->toArray(false);
        if ('ok' !== $jsonContents['status'] ?? null) {
            $errorMessage = $jsonContents['message'] ?? $response->getContent(false);

            throw new TransportException('Unable to post the Engagespot message: '.$errorMessage, $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
