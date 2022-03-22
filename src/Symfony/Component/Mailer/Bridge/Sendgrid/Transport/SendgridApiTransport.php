<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class SendgridApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.sendgrid.com';

    private $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('sendgrid+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v3/mail/send', [
            'json' => $this->getPayload($email, $envelope),
            'auth_bearer' => $this->key,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Sendgrid server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            try {
                $result = $response->toArray(false);

                throw new HttpTransportException('Unable to send an email: '.implode('; ', array_column($result['errors'], 'message')).sprintf(' (code %d).', $statusCode), $response);
            } catch (DecodingExceptionInterface $e) {
                throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response, 0, $e);
            }
        }

        $sentMessage->setMessageId($response->getHeaders(false)['x-message-id'][0]);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $addressStringifier = function (Address $address) {
            $stringified = ['email' => $address->getAddress()];

            if ($address->getName()) {
                $stringified['name'] = $address->getName();
            }

            return $stringified;
        };

        $payload = [
            'personalizations' => [],
            'from' => $addressStringifier($envelope->getSender()),
            'content' => $this->getContent($email),
        ];

        if ($email->getAttachments()) {
            $payload['attachments'] = $this->getAttachments($email);
        }

        $personalization = [
            'to' => array_map($addressStringifier, $this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];
        if ($emails = array_map($addressStringifier, $email->getCc())) {
            $personalization['cc'] = $emails;
        }
        if ($emails = array_map($addressStringifier, $email->getBcc())) {
            $personalization['bcc'] = $emails;
        }
        if ($emails = array_map($addressStringifier, $email->getReplyTo())) {
            // Email class supports an array of reply-to addresses,
            // but SendGrid only supports a single address
            $payload['reply_to'] = $emails[0];
        }

        $payload['personalizations'][] = $personalization;

        // these headers can't be overwritten according to Sendgrid docs
        // see https://sendgrid.api-docs.io/v3.0/mail-send/mail-send-errors#-Headers-Errors
        $headersToBypass = ['x-sg-id', 'x-sg-eid', 'received', 'dkim-signature', 'content-transfer-encoding', 'from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'reply-to'];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            $payload['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
    }

    private function getContent(Email $email): array
    {
        $content = [];
        if (null !== $text = $email->getTextBody()) {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }
        if (null !== $html = $email->getHtmlBody()) {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        return $content;
    }

    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
                'type' => $headers->get('Content-Type')->getBody(),
                'filename' => $filename,
                'disposition' => $disposition,
            ];

            if ('inline' === $disposition) {
                $att['content_id'] = $filename;
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
