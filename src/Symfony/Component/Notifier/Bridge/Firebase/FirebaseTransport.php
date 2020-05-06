<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 *
 * @experimental in 5.1
 */
final class FirebaseTransport extends AbstractTransport
{
    protected const HOST = 'fcm.googleapis.com/fcm/send';

    /** @var string */
    private $token;

    public function __construct(string $token, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('firebase://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof ChatMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, ChatMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['to'])) {
            $options['to'] = $message->getRecipientId();
        }
        if (null === $options['to']) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "to" option to be set.', __CLASS__));
        }
        $options['notification'] = $options['notification'] ?? [];
        $options['notification']['body'] = $message->getSubject();
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => sprintf('key=%s', $this->token),
            ],
            'json' => array_filter($options),
        ]);

        $contentType = $response->getHeaders(false)['Content-Type'] ?? '';
        $jsonContents = 0 === strpos($contentType, 'application/json') ? $response->toArray(false) : null;

        if (200 !== $response->getStatusCode()) {
            $errorMessage = $jsonContents ? $jsonContents['results']['error'] : $response->getContent(false);

            throw new TransportException(sprintf('Unable to post the Firebase message: %s.', $errorMessage), $response);
        }
        if ($jsonContents && isset($jsonContents['results']['error'])) {
            throw new TransportException(sprintf('Unable to post the Firebase message: %s.', $jsonContents['error']), $response);
        }
    }
}
