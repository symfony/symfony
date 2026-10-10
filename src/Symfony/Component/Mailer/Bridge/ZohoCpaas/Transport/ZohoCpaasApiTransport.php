<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\ZohoCpaas\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends through the email API of Zoho CPaaS, formerly ZeptoMail.
 *
 * @see https://www.zoho.com/cpaas/help/api/email-sending.html
 *
 * @author Dennis Petersmann <dennis@petersmann.dev>
 */
final class ZohoCpaasApiTransport extends AbstractApiTransport
{
    private const HOST = 'cpaas.zoho.com';

    /**
     * Headers that the payload already contains or that Symfony generates. Other headers are sent as MIME headers.
     */
    private const PAYLOAD_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'sender', 'date', 'message-id', 'return-path',
        'content-type', 'content-transfer-encoding', 'mime-version', 'x-track',
    ];

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('zohocpaas+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1.1/email', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Zoho-enczapikey '.$this->token,
            ],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Zoho CPaaS server.', $response, 0, $e);
        }

        if (200 !== $statusCode && 201 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$this->getErrorMessage($result, $response).\sprintf(' (code %d).', $statusCode), $response);
        }

        if (isset($result['request_id'])) {
            $sentMessage->setMessageId((string) $result['request_id']);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => $this->formatRecipients($this->getRecipients($email, $envelope)),
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = $this->formatRecipients($cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = $this->formatRecipients($bcc);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = array_map($this->formatAddress(...), $replyTo);
        }

        if (null !== $email->getSubject()) {
            $payload['subject'] = $email->getSubject();
        }

        if (null !== $html = $this->bodyToString($email->getHtmlBody())) {
            $payload['htmlbody'] = $html;
        }

        if (null !== $text = $this->bodyToString($email->getTextBody())) {
            $payload['textbody'] = $text;
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $part = [
                'content' => base64_encode($attachment->getBody()),
                'mime_type' => $headers->get('Content-Type')->getBody(),
                'name' => $filename,
            ];

            // A part that the HTML refers to is inline. Symfony marks it as inline only when it builds the MIME body.
            $referenced = null === $html ? null : $this->getReferencedCid($attachment, $html);

            if (null !== $referenced || 'inline' === $headers->getHeaderBody('Content-Disposition')) {
                $part['cid'] = $referenced ?? ($attachment->hasContentId() ? $attachment->getContentId() : $filename);
                $payload['inline_images'][] = $part;
            } else {
                $payload['attachments'][] = $part;
            }
        }

        if ($tracking = TrackingHeader::fromHeaders($email->getHeaders())) {
            if (null !== $tracking->getOpens()) {
                $payload['track_opens'] = $tracking->getOpens();
            }
            if (null !== $tracking->getClicks()) {
                $payload['track_clicks'] = $tracking->getClicks();
            }
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, self::PAYLOAD_HEADERS, true)) {
                continue;
            }

            $payload['mime_headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
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

        // Not "?: ''": the content "0" is as valid as any other
        $contents = stream_get_contents($body);

        return false === $contents ? '' : $contents;
    }

    /**
     * Returns the reference the HTML uses for the part: its name or its content id.
     *
     * The HTML is sent as is, so the API must get the same reference. A content id generated by Symfony would break it, because Symfony only rewrites the HTML of the MIME body.
     * As in Symfony, "cid:" is matched in any case and the reference exactly.
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
     * @param Address[] $addresses
     */
    private function formatRecipients(array $addresses): array
    {
        return array_map(fn (Address $address): array => ['email_address' => $this->formatAddress($address)], $addresses);
    }

    private function formatAddress(Address $address): array
    {
        $formatted = ['address' => $address->getAddress()];

        if ('' !== $address->getName()) {
            $formatted['name'] = $address->getName();
        }

        return $formatted;
    }

    /**
     * Returns the error message of the API with its codes.
     *
     * The codes show, for example, whether the sender domain is not verified or the credits are exhausted.
     * Failures come as an "error" object with "details", as documented for ZeptoMail. The current reference of Zoho CPaaS shows a "data" object with an "error_code" instead.
     */
    private function getErrorMessage(array $result, ResponseInterface $response): string
    {
        if (\is_array($error = $result['error'] ?? null) && isset($error['message'])) {
            $codes = [$error['code'] ?? null];

            foreach ((array) ($error['details'] ?? []) as $detail) {
                if (\is_array($detail)) {
                    $codes[] = trim(($detail['code'] ?? '').' '.($detail['message'] ?? ''));
                }
            }

            return self::withCodes((string) $error['message'], $codes);
        }

        if (\is_array($data = $result['data'] ?? null) && isset($data['message'])) {
            return self::withCodes((string) $data['message'], [$data['error_code'] ?? null]);
        }

        return $response->getContent(false);
    }

    private static function withCodes(string $message, array $codes): string
    {
        return ($codes = array_filter($codes)) ? $message.' ['.implode('; ', $codes).']' : $message;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
