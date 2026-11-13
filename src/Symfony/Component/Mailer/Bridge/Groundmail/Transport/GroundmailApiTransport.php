<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends through the HTTP API of Groundmail.
 *
 * The X-Groundmail-Category, X-Groundmail-Scheduled-At, X-Groundmail-Idempotency-Key and
 * X-Groundmail-Tag headers are the ones the Groundmail SMTP relay reads. This transport turns them
 * into the matching fields of the API, so a message is sent the same way on both transports.
 *
 * @see https://docs.groundmail.dev/api/emails
 *
 * @author Shivansh Mehendiratta <shivansh@backslope.studio>
 */
final class GroundmailApiTransport extends AbstractApiTransport implements RemoteTemplateTransportInterface
{
    public const HEADER_CATEGORY = 'X-Groundmail-Category';
    public const HEADER_SCHEDULED_AT = 'X-Groundmail-Scheduled-At';
    public const HEADER_IDEMPOTENCY_KEY = 'X-Groundmail-Idempotency-Key';
    public const HEADER_TAG = 'X-Groundmail-Tag';

    private const HOST = 'api.groundmail.dev';

    /**
     * Headers that the payload already contains or that Symfony generates. Other headers are sent in "headers".
     */
    private const PAYLOAD_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'sender', 'date', 'message-id', 'return-path',
        'content-type', 'content-transfer-encoding', 'mime-version',
        'x-groundmail-category', 'x-groundmail-scheduled-at', 'x-groundmail-idempotency-key', 'x-groundmail-tag',
    ];

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('groundmail+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiKey,
        ];

        if (null !== $idempotencyKey = $this->getHeaderValue($email, self::HEADER_IDEMPOTENCY_KEY)) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/emails', [
            'headers' => $headers,
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Groundmail server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$this->getErrorMessage($result, $response).\sprintf(' (code %d).', $statusCode), $response);
        }

        if (isset($result['id'])) {
            $sentMessage->setMessageId((string) $result['id']);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;

        $to = $this->getRecipients($email, $envelope);
        if (!$to) {
            throw new InvalidArgumentException('The Groundmail API needs at least one "To" recipient.');
        }

        // The API sets the visible From header, and checks it against the verified domains of the account
        $from = $email->getFrom()[0] ?? $envelope->getSender();

        $payload = [
            'from' => '' !== $from->getName() ? ['email' => $from->getEncodedAddress(), 'name' => $from->getName()] : $from->getEncodedAddress(),
            'to' => $this->formatAddresses($to),
        ];

        // Cc and Bcc addresses that the envelope no longer carries, for example after a redirect by framework.mailer.envelope.recipients, are dropped
        if ($cc = $this->filterByEnvelope($email->getCc(), $envelope)) {
            $payload['cc'] = $this->formatAddresses($cc);
        }

        if ($bcc = $this->filterByEnvelope($email->getBcc(), $envelope)) {
            $payload['bcc'] = $this->formatAddresses($bcc);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->formatAddresses($replyTo);
        }

        if (null !== $email->getSubject()) {
            $payload['subject'] = $email->getSubject();
        }

        if (null !== $template) {
            $payload['template'] = ['id' => $template->getReference()];
            if ($template->getVariables()) {
                $payload['template']['variables'] = $template->getVariables();
            }
        }

        $html = $this->bodyToString($email->getHtmlBody());
        if (null !== $html) {
            $payload['html'] = $html;
        }

        if (null !== $text = $this->bodyToString($email->getTextBody())) {
            $payload['text'] = $text;
        }

        if ($attachments = $this->getAttachments($email, $html)) {
            $payload['attachments'] = $attachments;
        }

        if (null !== $category = $this->getHeaderValue($email, self::HEADER_CATEGORY)) {
            $payload['category'] = $category;
        }

        if (null !== $scheduledAt = $this->getHeaderValue($email, self::HEADER_SCHEDULED_AT)) {
            $payload['scheduled_at'] = $scheduledAt;
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if ($header instanceof MetadataHeader) {
                $payload['tags'][] = ['name' => $header->getKey(), 'value' => $header->getValue()];

                continue;
            }

            if ('x-groundmail-tag' === $name) {
                $payload['tags'][] = $this->parseTag($header->getBodyAsString());

                continue;
            }

            if (\in_array($name, self::PAYLOAD_HEADERS, true)) {
                continue;
            }

            $payload['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
    }

    /**
     * @return list<array{name: string, content_type: string, data: string, content_id?: string}>
     */
    private function getAttachments(Email $email, ?string $html): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $part = [
                'name' => $filename,
                'content_type' => $headers->get('Content-Type')->getBody(),
                'data' => base64_encode($attachment->getBody()),
            ];

            // A part that the HTML refers to is inline. Symfony marks it as inline only when it builds the MIME body.
            $referenced = null === $html ? null : $this->getReferencedCid($attachment, $html);

            if (null !== $referenced || 'inline' === $headers->getHeaderBody('Content-Disposition')) {
                $part['content_id'] = $referenced ?? ($attachment->hasContentId() ? $attachment->getContentId() : $filename);
            }

            $attachments[] = $part;
        }

        return $attachments;
    }

    /**
     * Returns the reference the HTML uses for the part: its name or its content id.
     *
     * The HTML is sent as is, so the API must get the same reference. A content id generated by Symfony would break it, because Symfony only rewrites the HTML of the MIME body.
     */
    private function getReferencedCid(DataPart $part, string $html): ?string
    {
        foreach ([$part->getName(), $part->hasContentId() ? $part->getContentId() : null] as $reference) {
            if (null !== $reference && 1 === preg_match('/\b(?i:cid:)'.preg_quote($reference, '/').'(?=[\s"\'>]|$)/', $html)) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * Mail bodies may be streams, which the JSON encoding cannot take.
     *
     * @param resource|string|null $body
     */
    private function bodyToString(mixed $body): ?string
    {
        if (!\is_resource($body)) {
            return $body;
        }

        if (stream_get_meta_data($body)['seekable']) {
            rewind($body);
        }

        $contents = stream_get_contents($body);

        return false === $contents ? '' : $contents;
    }

    /**
     * @return array{name: string, value: string}
     */
    private function parseTag(string $value): array
    {
        [$name, $tagValue] = explode('=', $value, 2) + [1 => ''];
        $name = trim($name);
        $tagValue = trim($tagValue);

        if ('' === $name || '' === $tagValue) {
            throw new InvalidArgumentException(\sprintf('The "%s" header must be written as "name=value", "%s" given.', self::HEADER_TAG, $value));
        }

        return ['name' => $name, 'value' => $tagValue];
    }

    private function getHeaderValue(Email $email, string $name): ?string
    {
        if (!$header = $email->getHeaders()->get($name)) {
            return null;
        }

        return '' === ($value = trim($header->getBodyAsString())) ? null : $value;
    }

    /**
     * @param Address[] $addresses
     *
     * @return Address[]
     */
    private function filterByEnvelope(array $addresses, Envelope $envelope): array
    {
        $allowed = array_map(static fn (Address $address): string => strtolower($address->getAddress()), $envelope->getRecipients());

        return array_values(array_filter($addresses, static fn (Address $address): bool => \in_array(strtolower($address->getAddress()), $allowed, true)));
    }

    /**
     * @param Address[] $addresses
     *
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map($this->formatAddress(...), $addresses));
    }

    /**
     * The API takes "Name <address>", with the name in double quotes when it has special characters.
     */
    private function formatAddress(Address $address): string
    {
        if ('' === $name = $address->getName()) {
            return $address->getEncodedAddress();
        }

        if (preg_match('/[()<>\[\]:;@\\\\,."]/', $name)) {
            $name = '"'.addcslashes($name, '"\\').'"';
        }

        return $name.' <'.$address->getEncodedAddress().'>';
    }

    /**
     * Errors come as an "error" object with a "code" and a "message". A few endpoints return "error" as a string, with the same fields beside it.
     *
     * @see https://docs.groundmail.dev/errors
     */
    private function getErrorMessage(array $result, ResponseInterface $response): string
    {
        $error = \is_array($result['error'] ?? null) ? $result['error'] : $result;

        if (!isset($error['message']) || !\is_string($error['message'])) {
            return $response->getContent(false);
        }

        return isset($error['code']) && \is_string($error['code']) ? $error['message'].' ['.$error['code'].']' : $error['message'];
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
