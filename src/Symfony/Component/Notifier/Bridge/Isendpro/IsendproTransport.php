<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Isendpro;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IsendproTransport extends AbstractTransport
{
    protected const HOST = 'apirest.isendpro.com';

    public function __construct(
        #[\SensitiveParameter] private string $keyid,
        private ?string $from = null,
        private bool $noStop = false,
        private bool $sandbox = false,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->from) {
            return sprintf('isendpro://%s?no_stop=%d&sandbox=%d', $this->getEndpoint(), (int) $this->noStop, (int) $this->sandbox);
        }

        return sprintf('isendpro://%s?from=%s&no_stop=%d&sandbox=%d', $this->getEndpoint(), $this->from, (int) $this->noStop, (int) $this->sandbox);
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

        $messageId = bin2hex(random_bytes(7));

        $messageData = [
            'keyid' => $this->keyid,
            'num' => $message->getPhone(),
            'sms' => $message->getSubject(),
            'sandbox' => (int) $this->sandbox,
            'tracker' => $messageId,
        ];

        if ($this->noStop) {
            $messageData['nostop'] = '1';
        }

        if ('' !== $message->getFrom()) {
            $messageData['emetteur'] = $message->getFrom();
        } elseif (null !== $this->from) {
            $messageData['emetteur'] = $this->from;
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/cgi-bin/sms', [
            'headers' => [
                'Accept' => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
            'json' => $messageData,
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
            $details = $result['etat']['etat'][0] ?? [];
            $detailsCode = (int) ($details['code'] ?? -1); // -1 is not a valid error code on iSendPro. But if code doesn't exist, it's a very strange error (not possible normally)

            if (200 === $statusCode && 0 === $detailsCode) {
                $sentMessage = new SentMessage($message, (string) $this);
                $sentMessage->setMessageId($messageId);

                return $sentMessage;
            }

            $errorMessage = sprintf('Unable to send the SMS: error %d.', $statusCode);
            $detailsMessage = $details['message'] ?? null;

            if ($detailsMessage) {
                $errorMessage .= sprintf(' Details from iSendPro: %s: "%s".', $detailsCode, $detailsMessage);
            }
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote iSendPro server.', $response, 0, $e);
        } catch (DecodingExceptionInterface $e) {
            $errorMessage = sprintf('Unable to send the SMS: error %d. %s', $statusCode, $e->getMessage());
        }

        throw new TransportException($errorMessage, $response);
    }
}
