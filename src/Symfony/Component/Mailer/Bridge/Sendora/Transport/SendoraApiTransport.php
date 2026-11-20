<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends through the email API of Sendora.
 *
 * @see https://sendora.se/docs/sending
 */
final class SendoraApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.sendora.se';

    /**
     * Headers that the payload already contains or that Sendora writes itself. Other headers are sent as custom headers.
     */
    private const PAYLOAD_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'subject', 'sender', 'return-path', 'date', 'message-id',
        'mime-version', 'content-type', 'content-transfer-encoding', 'x-track',
    ];

    private ?string $stream = null;

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
        return \sprintf('sendora+api://%s', $this->getEndpoint());
    }

    /**
     * @param string $stream The id of the stream the messages go on, instead of the server's default transactional stream
     *
     * @return $this
     */
    public function setStream(string $stream): static
    {
        $this->stream = $stream;

        return $this;
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1/email', [
            'auth_bearer' => $this->key,
            // Sendora answers a request again under the same key with its first answer, so a retrying HTTP client cannot send the email twice
            'headers' => ['Idempotency-Key' => bin2hex(random_bytes(16))],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Sendora server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.($result['message'] ?? $response->getContent(false)).\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['messageId']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        // The visible sender, which must be on a verified domain; the envelope sender may come from the Sender or Return-Path header
        $payload = [
            'from' => $this->formatAddress($email->getFrom()[0] ?? $envelope->getSender()),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope)),
        ];

        // Only the envelope's recipients get the message, as over SMTP, so that a redirect of the recipients also stops the copies
        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $field => $addresses) {
            if ($addresses = array_filter($addresses, static fn (Address $address): bool => \in_array($address, $envelope->getRecipients(), true))) {
                $payload[$field] = $this->formatAddresses($addresses);
            }
        }

        if (null !== $email->getSubject()) {
            $payload['subject'] = $email->getSubject();
        }

        if (null !== $text = $this->getBody($email->getTextBody())) {
            $payload['text'] = $text;
        }

        if (null !== $html = $this->getBody($email->getHtmlBody())) {
            $payload['html'] = $html;
        }

        if (null !== $this->stream) {
            $payload['streamId'] = $this->stream;
        }

        foreach ($email->getAttachments() as $attachment) {
            $part = [
                'name' => $attachment->getFilename(),
                'content' => base64_encode($attachment->getBody()),
                'contentType' => $attachment->getContentType(),
            ];

            if (null !== $contentId = $this->getContentId($attachment, $html)) {
                $part['contentId'] = $contentId;
            }

            $payload['attachments'][] = $part;
        }

        $tags = [];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if ('x-tag' === $name) {
                $tags[] = $this->getHeaderValue($header);
            } elseif (str_starts_with($name, 'x-metadata-')) {
                $payload['metadata'][substr($header->getName(), \strlen('X-Metadata-'))] = $this->getHeaderValue($header);
            } elseif (!\in_array($name, self::PAYLOAD_HEADERS, true)) {
                $payload['headers'][$header->getName()] = $this->getHeaderValue($header);
            }
        }

        if (\count($tags = array_unique($tags)) > 1) {
            throw new TransportException('Sendora allows a single tag per email.');
        }

        if ($tags) {
            $payload['tag'] = $tags[0];
        }

        if ($tracking = TrackingHeader::fromHeaders($email->getHeaders())) {
            if (null !== $tracking->getOpens()) {
                $payload['trackOpens'] = $tracking->getOpens();
            }
            if (null !== $tracking->getClicks()) {
                $payload['trackLinks'] = $tracking->getClicks();
            }
        }

        return $payload;
    }

    /**
     * Returns the reference that the HTML uses for the part: its name or its content id.
     *
     * The HTML is sent as is, and Sendora makes each part with a content id an inline part of it. Symfony rewrites the
     * "cid:" references only in the MIME body, and gives a part a content id of its own when it builds that body.
     * As in Symfony, "cid:" is matched in any case and the reference exactly.
     */
    private function getContentId(DataPart $part, ?string $html): ?string
    {
        $contentId = $part->hasContentId() ? $part->getContentId() : null;

        foreach ([$part->getName(), $contentId] as $reference) {
            if (null !== $reference && null !== $html && 1 === preg_match('/\b(?i:cid:)'.preg_quote($reference, '/').'(?=[\s"\'>]|$)/', $html)) {
                return $reference;
            }
        }

        return 'inline' === $part->getDisposition() ? $contentId ?? $part->getName() : null;
    }

    /**
     * The API takes a header value on one line and unencoded, where getBodyAsString() would fold a long one and encode words outside ASCII.
     */
    private function getHeaderValue(HeaderInterface $header): string
    {
        if ($header instanceof MailboxListHeader) {
            return implode(', ', $this->stringifyAddresses($header->getAddresses()));
        }

        if ($header instanceof UnstructuredHeader && !$header instanceof ParameterizedHeader) {
            return $header->getValue();
        }

        return $header->getBodyAsString();
    }

    /**
     * Mail bodies may be streams, which the JSON encoding cannot take, and the API refuses an empty part.
     *
     * @param resource|string|null $body
     */
    private function getBody(mixed $body): ?string
    {
        if (\is_resource($body)) {
            if (stream_get_meta_data($body)['seekable']) {
                rewind($body);
            }

            $body = stream_get_contents($body);
        }

        return '' === $body || false === $body ? null : $body;
    }

    /**
     * @param Address[] $addresses
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map($this->formatAddress(...), array_values($addresses));
    }

    private function formatAddress(Address $address): array
    {
        $formatted = ['email' => $address->getEncodedAddress()];

        // The API refuses an empty name
        if ('' !== $address->getName()) {
            $formatted['name'] = $address->getName();
        }

        return $formatted;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
