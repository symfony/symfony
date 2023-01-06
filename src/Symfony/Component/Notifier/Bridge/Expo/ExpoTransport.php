<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo;

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
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 */
final class ExpoTransport extends AbstractTransport
{
    protected const HOST = 'exp.host/--/api/v2/push/send';

    /** @var string|null */
    private $token;

    public function __construct(#[\SensitiveParameter] string $token = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('expo://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage;
    }

    /**
     * @see https://docs.expo.dev/push-notifications/sending-notifications/#http2-api
     */
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
        if (null === $options['to']) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "to" option to be set.', __CLASS__));
        }

        $options['title'] = $message->getSubject();
        $options['body'] = $message->getContent();
        $options['data'] ??= [];

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => $this->token ? sprintf('Bearer %s', $this->token) : null,
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Expo server.', $response, 0, $e);
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $jsonContents = str_starts_with($contentType, 'application/json') ? $response->toArray(false) : null;

        if (200 !== $statusCode) {
            $errorMessage = $jsonContents['error']['message'] ?? $response->getContent(false);

            throw new TransportException('Unable to post the Expo message: '.$errorMessage, $response);
        }

        $result = $response->toArray(false);

        if ('error' === $result['data']['status']) {
            throw new TransportException(sprintf('Unable to post the Expo message: "%s" (%s)', $result['data']['message'], $result['data']['details']['error']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['data']['id']);

        return $sentMessage;
    }
}
