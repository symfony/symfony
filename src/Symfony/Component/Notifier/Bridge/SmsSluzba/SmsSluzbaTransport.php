<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsSluzba;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Dennis Fridrich <fridrich.dennis@gmail.com>
 */
final class SmsSluzbaTransport extends AbstractTransport
{
    protected const HOST = 'smsgateapi.sms-sluzba.cz';

    public function __construct(
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $password,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('sms-sluzba://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof SmsSluzbaOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = \sprintf(
            'https://%s/apixml30/receiver?login=%s&password=%s',
            $this->getEndpoint(),
            $this->username,
            $this->password
        );

        $options = $message->getOptions()?->toArray() ?? [];

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body' => [
                'outgoing_message' => [
                    'dr_request' => 20, // 0 = delivery report is not required; 20 = delivery report is required
                    'recipient' => $message->getPhone(),
                    'text' => $message->getSubject(),
                    'send_at' => $options['send_at'] ?? null,
                ],
            ],
        ]);

        try {
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote sms-sluzba.cz server.', $response, 0, $e);
        }

        $xmlEncoder = new XmlEncoder();
        $responseXml = $xmlEncoder->decode($response->getContent(), 'xml');

        if (isset($responseXml['message']) && \is_string($responseXml['message'])) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $responseXml['message'], (int) substr($responseXml['id'], 0, 3)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($responseXml['message']['id']);

        return $sentMessage;
    }
}
