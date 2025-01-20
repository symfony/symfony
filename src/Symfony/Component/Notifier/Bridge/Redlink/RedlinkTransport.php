<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Redlink;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
final class RedlinkTransport extends AbstractTransport
{
    protected const HOST = 'api.redlink.pl';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiToken,
        #[\SensitiveParameter] private readonly string $appToken,
        private readonly ?string $from,
        private readonly ?string $version,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function __toString(): string
    {
        return \sprintf(
            'redlink://%s?from=%s&version=%s',
            $this->getEndpoint(),
            $this->from,
            $this->version
        );
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];

        $from = $message->getFrom() ?: $this->from;

        $endpoint = \sprintf('https://%s/%s/sms', $this->getEndpoint(), $this->version);

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => $this->apiToken,
                'Application-Key' => $this->appToken,
            ],
            'json' => array_merge([
                'sender' => $from,
                'message' => $message->getSubject(),
                'phoneNumbers' => [
                    $message->getPhone(),
                ],
            ], array_filter($options)),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Redlink server.', $response, 0, $e);
        }

        $content = $response->toArray(false);

        if (200 !== $statusCode) {
            $requestUniqueIdentifier = $content['meta']['uniqId'] ?? '';

            $errorMessage = $content['errors'][0]['message'] ?? '';

            throw new TransportException(\sprintf('Unable to send the SMS: '.$errorMessage.'. UniqId: (%s).', $requestUniqueIdentifier), $response);
        }

        $messageId = $content['data'][0]['externalId'] ?? '';

        $sentMessage = new SentMessage($message, (string) $this);

        $sentMessage->setMessageId($messageId);

        return $sentMessage;
    }
}
