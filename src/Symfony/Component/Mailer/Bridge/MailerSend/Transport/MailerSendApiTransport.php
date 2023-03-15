<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see https://developers.mailersend.com/api/v1/email.html
 */
final class MailerSendApiTransport extends AbstractApiTransport
{
    private string $key;

    public function __construct(#[\SensitiveParameter] string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mailersend+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1/email', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'Bearer '.$this->key,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote MailerSend server.', $response, 0, $e);
        }

        try {
            $result = '' !== $content ? $response->toArray(false) : null;
        } catch (JsonException $e) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $content, $statusCode), $response, 0, $e);
        }

        if (202 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.($result['message'] ?? '').sprintf(' (code %d).', $statusCode), $response);
        }

        if (isset($result['warnings'][0]['type']) && 'ALL_SUPPRESSED' === $result['warnings'][0]['type']) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'] ?? 'All suppressed', $response);
        }

        if (isset($headers['x-message-id'][0])) {
            $sentMessage->setMessageId($headers['x-message-id'][0]);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $sender = $envelope->getSender();

        $payload = [
            'from' => array_filter([
                'email' => $sender->getAddress(),
                'name' => $sender->getName(),
            ]),
            'to' => $this->prepareAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];

        if ($attachments = $this->prepareAttachments($email)) {
            $payload['attachments'] = $attachments;
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = current($this->prepareAddresses($replyTo));
        }

        if ($cc = $email->getCc()) {
            $payload['cc'] = $this->prepareAddresses($cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = $this->prepareAddresses($bcc);
        }

        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $payload['html'] = $email->getHtmlBody();
        }

        return $payload;
    }

    /**
     * @param Address[] $addresses
     */
    private function prepareAddresses(array $addresses): array
    {
        $recipients = [];

        foreach ($addresses as $address) {
            $recipients[] = [
                'email' => $address->getAddress(),
                'name' => $address->getName(),
            ];
        }

        return $recipients;
    }

    private function prepareAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $att = [
                'content' => $attachment->bodyToString(),
                'filename' => $filename,
            ];

            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                $att['disposition'] = 'inline';
                $att['id'] = $attachment->getContentId();
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: 'api.mailersend.com').($this->port ? ':'.$this->port : '');
    }
}
