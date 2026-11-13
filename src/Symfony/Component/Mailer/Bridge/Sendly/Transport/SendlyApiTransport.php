<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendly\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Amin Dhouib <amin@devino.ca>
 */
final class SendlyApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.sendly.now';

    /**
     * Header names the API refuses in the custom headers map, because it writes them itself.
     */
    private const RESERVED_HEADERS = [
        'from', 'sender', 'reply-to', 'to', 'cc', 'bcc', 'subject', 'date', 'message-id', 'mime-version',
        'content-type', 'content-transfer-encoding', 'content-disposition', 'return-path', 'dkim-signature',
        'domainkey-signature', 'received', 'authentication-results', 'list-unsubscribe', 'list-unsubscribe-post',
    ];

    private const RESERVED_HEADER_PREFIXES = ['x-ses-', 'x-sendly-', 'resent-', 'arc-'];

    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('sendly+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        // The API takes exactly one "to" address per request, so every recipient gets its own request.
        /** @var non-empty-list<array<string, mixed>> $payloads */
        $payloads = $this->getPayloads($email, $envelope);

        foreach ($payloads as $index => $payload) {
            $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/v1/emails', [
                'auth_bearer' => $this->key,
                'json' => $payload,
            ]);

            try {
                $statusCode = $response->getStatusCode();
                $result = $response->toArray(false);
            } catch (DecodingExceptionInterface) {
                throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
            } catch (TransportExceptionInterface $e) {
                throw new HttpTransportException('Could not reach the remote Sendly server.', $response, 0, $e);
            }

            if (202 !== $statusCode) {
                throw new HttpTransportException('Unable to send an email: '.$this->getErrorMessage($result, $response).\sprintf(' (code %d).', $statusCode), $response);
            }

            if (0 === $index && isset($result['id'])) {
                $sentMessage->setMessageId($result['id']);
            }
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>> one payload per recipient
     */
    private function getPayloads(Email $email, Envelope $envelope): array
    {
        // The visible From header is what the API checks against a verified domain. The envelope
        // sender is Sender or Return-Path first, so it could be a bounce address instead.
        $from = $email->getFrom();
        $shared = [
            'from' => $this->formatAddress($from ? $from[0] : $envelope->getSender()),
            'body' => $email->getHtmlBody() ?? $email->getTextBody(),
            'subject' => $email->getSubject(),
        ];

        if ($replyTo = $email->getReplyTo()) {
            if (\count($replyTo) > 1) {
                throw new InvalidArgumentException('Sendly accepts a single Reply-To address.');
            }

            $shared['reply'] = $replyTo[0]->getAddress();
        }

        if ($attachments = $this->getAttachments($email)) {
            $shared['attachments'] = $attachments;
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof TagHeader) {
                $shared['tags'][] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader || $this->isReservedHeader($name)) {
                continue;
            }

            $shared['headers'][$header->getName()] = $header->getBodyAsString();
        }

        // The envelope is what gets delivered: framework.mailer.envelope.recipients rewrites it
        // and not the headers. Cc and Bcc are attached to the first message only, otherwise
        // every To recipient would trigger another copy for them.
        $recipients = $this->getRecipients($email, $envelope);
        $cc = $this->getCopies($email->getCc(), $envelope);
        $bcc = $this->getCopies($email->getBcc(), $envelope);
        if (!$recipients) {
            // Only Cc and Bcc recipients: each one becomes the "to" of its own message
            $recipients = $envelope->getRecipients();
            $cc = $bcc = [];
        }

        $payloads = [];
        foreach ($recipients as $index => $recipient) {
            $payload = ['to' => $this->formatAddress($recipient)] + $shared;
            if (0 === $index) {
                if ($cc) {
                    $payload['cc'] = $cc;
                }
                if ($bcc) {
                    $payload['bcc'] = $bcc;
                }
            }
            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @return string|array{email: string, name: string}
     */
    private function formatAddress(Address $address): string|array
    {
        if ('' === $address->getName()) {
            return $address->getAddress();
        }

        return ['email' => $address->getAddress(), 'name' => $address->getName()];
    }

    /**
     * Copy recipients that the envelope still carries, so a redirected envelope does not keep
     * mailing the original ones.
     *
     * @param Address[] $addresses
     *
     * @return list<string>
     */
    private function getCopies(array $addresses, Envelope $envelope): array
    {
        $allowed = array_map(static fn (Address $address): string => $address->getAddress(), $envelope->getRecipients());

        $copies = [];
        foreach ($addresses as $address) {
            if (\in_array($address->getAddress(), $allowed, true)) {
                $copies[] = $address->getAddress();
            }
        }

        return $copies;
    }

    private function isReservedHeader(string $name): bool
    {
        if (\in_array($name, self::RESERVED_HEADERS, true)) {
            return true;
        }

        foreach (self::RESERVED_HEADER_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $item = [
                'filename' => $headers->getHeaderParameter('Content-Disposition', 'filename'),
                'content' => base64_encode($attachment->getBody()),
                'contentType' => $headers->get('Content-Type')->getBody(),
                'disposition' => $disposition,
            ];

            if ('inline' === $disposition) {
                $item['contentId'] = $attachment->hasContentId() ? $attachment->getContentId() : $item['filename'];
            }

            $attachments[] = $item;
        }

        return $attachments;
    }

    private function getErrorMessage(array $result, ResponseInterface $response): string
    {
        // Problem document of the /api/v1 routes, or the older {"error": {"message": ...}} envelope
        $message = $result['detail'] ?? $result['error']['message'] ?? null;
        if (null === $message) {
            return $response->getContent(false);
        }

        foreach ($result['errors'] ?? [] as $error) {
            $message .= \sprintf(' %s: %s.', $error['pointer'] ?? '', $error['message'] ?? '');
        }

        return $message;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
