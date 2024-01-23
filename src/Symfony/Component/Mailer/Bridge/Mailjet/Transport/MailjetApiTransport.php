<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailjetApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.mailjet.com';
    private const API_VERSION = '3.1';
    private const FORBIDDEN_HEADERS = [
        'Date', 'X-CSA-Complaints', 'Message-Id', 'X-Mailjet-Campaign', 'X-MJ-StatisticsContactsListID',
        'DomainKey-Status', 'Received-SPF', 'Authentication-Results', 'Received', 'X-Mailjet-Prio',
        'From', 'Sender', 'Subject', 'To', 'Cc', 'Bcc', 'Reply-To', 'Return-Path', 'Delivered-To', 'DKIM-Signature',
        'X-Feedback-Id', 'X-Mailjet-Segmentation', 'List-Id', 'X-MJ-MID', 'X-MJ-ErrorMessage',
        'X-MJ-TemplateErrorDeliver', 'X-MJ-TemplateErrorReporting', 'X-MJ-TemplateLanguage',
        'X-Mailjet-Debug', 'User-Agent', 'X-Mailer', 'X-MJ-CustomID', 'X-MJ-EventPayload', 'X-MJ-Vars',
        'X-Mailjet-TrackOpen', 'X-Mailjet-TrackClick', 'X-MJ-TemplateID', 'X-MJ-WorkflowID',
    ];

    private $privateKey;
    private $publicKey;

    public function __construct(string $publicKey, string $privateKey, ?HttpClientInterface $client = null, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mailjet+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', sprintf('https://%s/v%s/send', $this->getEndpoint(), self::API_VERSION), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'auth_basic' => $this->publicKey.':'.$this->privateKey,
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $response->getContent(false), $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mailjet server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $errorDetails = $result['Messages'][0]['Errors'][0]['ErrorMessage'] ?? $response->getContent(false);

            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $errorDetails, $statusCode), $response);
        }

        // The response needs to contains a 'Messages' key that is an array
        if (!\array_key_exists('Messages', $result) || !\is_array($result['Messages']) || 0 === \count($result['Messages'])) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" malformed api response.', $response->getContent(false)), $response);
        }

        $sentMessage->setMessageId($result['Messages'][0]['To'][0]['MessageID'] ?? '');

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $html = $email->getHtmlBody();
        if (null !== $html && \is_resource($html)) {
            if (stream_get_meta_data($html)['seekable'] ?? false) {
                rewind($html);
            }
            $html = stream_get_contents($html);
        }
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);

        $message = [
            'From' => $this->formatAddress($envelope->getSender()),
            'To' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'Subject' => $email->getSubject(),
            'Attachments' => $attachments,
            'InlinedAttachments' => $inlines,
        ];
        if ($emails = $email->getCc()) {
            $message['Cc'] = $this->formatAddresses($emails);
        }
        if ($emails = $email->getBcc()) {
            $message['Bcc'] = $this->formatAddresses($emails);
        }
        if ($emails = $email->getReplyTo()) {
            if (1 < $length = \count($emails)) {
                throw new TransportException(sprintf('Mailjet\'s API only supports one Reply-To email, %d given.', $length));
            }
            $message['ReplyTo'] = $this->formatAddress($emails[0]);
        }
        if ($email->getTextBody()) {
            $message['TextPart'] = $email->getTextBody();
        }
        if ($html) {
            $message['HTMLPart'] = $html;
        }

        foreach ($email->getHeaders()->all() as $header) {
            if (\in_array($header->getName(), self::FORBIDDEN_HEADERS, true)) {
                continue;
            }

            $message['Headers'][$header->getName()] = $header->getBodyAsString();
        }

        return [
            'Messages' => [$message],
        ];
    }

    private function formatAddresses(array $addresses): array
    {
        return array_map([$this, 'formatAddress'], $addresses);
    }

    private function formatAddress(Address $address): array
    {
        return [
            'Email' => $address->getAddress(),
            'Name' => $address->getName(),
        ];
    }

    private function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = $inlines = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $formattedAttachment = [
                'ContentType' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'Filename' => $filename,
                'Base64Content' => $attachment->bodyToString(),
            ];
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                $formattedAttachment['ContentID'] = $headers->getHeaderParameter('Content-Disposition', 'name');
                $inlines[] = $formattedAttachment;
            } else {
                $attachments[] = $formattedAttachment;
            }
        }

        return [$attachments, $inlines, $html];
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
