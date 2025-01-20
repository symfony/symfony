<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Clickatell;

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
 * @author Kevin Auvinet <k.auvinet@gmail.com>
 */
final class ClickatellTransport extends AbstractTransport
{
    protected const HOST = 'api.clickatell.com';

    public function __construct(
        #[\SensitiveParameter] private string $authToken,
        private ?string $from = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->from) {
            return \sprintf('clickatell://%s', $this->getEndpoint());
        }

        return \sprintf('clickatell://%s%s', $this->getEndpoint(), null !== $this->from ? '?from='.$this->from : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = \sprintf('https://%s/rest/message', $this->getEndpoint());

        $options = [];
        $options['from'] = $message->getFrom() ?: $this->from;
        $options['to'] = [$message->getPhone()];
        $options['text'] = $message->getSubject();

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->authToken,
                'Content-Type' => 'application/json',
                'X-Version' => 1,
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Clickatell server.', $response, 0, $e);
        }

        if (202 === $statusCode) {
            $result = $response->toArray();
            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($result['data']['message'][0]['apiMessageId']);

            return $sentMessage;
        }

        $content = $response->toArray(false);
        $errorCode = $content['error']['code'] ?? '';
        $errorInfo = $content['error']['description'] ?? '';
        $errorDocumentation = $content['error']['documentation'] ?? '';

        throw new TransportException(\sprintf('Unable to send SMS with Clickatell: Error code %d with message "%s" (%s).', $errorCode, $errorInfo, $errorDocumentation), $response);
    }
}
