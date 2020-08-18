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

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailjetApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.mailjet.com';
    private const API_VERSION = '3.1';

    private $privateKey;
    private $publicKey;

    public function __construct(string $publicKey, string $privateKey, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
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

        $result = $response->toArray(false);

        if (200 !== $response->getStatusCode()) {
            if ('application/json' === $response->getHeaders(false)['content-type'][0]) {
                throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $result['Message'], $response->getStatusCode()), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email: "%s" (code %d).', $response->getContent(false), $response->getStatusCode()), $response);
        }

        // The response needs to contains a 'Messages' key that is an array
        if (!\array_key_exists('Messages', $result) || !\is_array($result['Messages']) || 0 === \count($result['Messages'])) {
            throw new HttpTransportException(sprintf('Unable to send an email: "%s" malformed api response.', $response->getContent(false)), $response);
        }

        $sentMessage->setMessageId($response->getHeaders(false)['x-mj-request-guid'][0]);

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
            'From' => [
                'Email' => $envelope->getSender()->getAddress(),
                'Name' => $envelope->getSender()->getName(),
            ],
            'To' => array_map(function (Address $recipient) {
                return [
                    'Email' => $recipient->getAddress(),
                    'Name' => $recipient->getName(),
                ];
            }, $this->getRecipients($email, $envelope)),
            'Subject' => $email->getSubject(),
            'Attachments' => $attachments,
            'InlinedAttachments' => $inlines,
        ];
        if ($emails = $email->getCc()) {
            $message['Cc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($emails = $email->getBcc()) {
            $message['Bcc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($email->getTextBody()) {
            $message['TextPart'] = $email->getTextBody();
        }
        if ($html) {
            $message['HTMLPart'] = $html;
        }

        return [
            'Messages' => [$message],
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
