<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\AhaSend\Event\AhaSendDeliveryEvent;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Farhad Hedayatifard <farhad@ahasend.com>
 */
final class AhaSendApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.ahasend.com';
    private const LEGACY_HOST = 'send.ahasend.com';

    private bool $legacy;

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $client = null,
        private ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
        private readonly ?string $accountId = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);

        $this->legacy = !$accountId;

        if ($this->legacy && str_starts_with($apiKey, 'aha-sk-')) {
            throw new IncompleteDsnException('A v2 AhaSend API key requires an account id: use the "ahasend+api://API_KEY:ACCOUNT_ID@default" DSN.');
        }
    }

    public function __toString(): string
    {
        return \sprintf('ahasend+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        if ($this->legacy) {
            return $this->doSendLegacyApi($sentMessage, $email, $envelope);
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v2/accounts/'.$this->accountId.'/messages', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote AhaSend server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        // one entry per recipient, each with its own generated Message-ID
        $messageId = null;
        foreach ($result['data'] ?? [] as $message) {
            if (null === $messageId && !empty($message['id'])) {
                $messageId = $message['id'];
            }
            if (null !== $this->dispatcher && !empty($message['error'])) {
                $this->dispatcher->dispatch(new AhaSendDeliveryEvent($message['error']));
            }
        }
        if (null !== $messageId) {
            $sentMessage->setMessageId($messageId);
        }

        return $response;
    }

    private function doSendLegacyApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        trigger_deprecation('symfony/aha-send-mailer', '8.2', 'Sending through the legacy AhaSend v1 API is deprecated, use a v2 API key and add your account id to the DSN.');

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1/email/send', [
            'json' => $this->getLegacyPayload($email, $envelope),
            'headers' => [
                'X-Api-Key' => $this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote AhaSend server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        if (null !== $this->dispatcher && \array_key_exists('fail_count', $result) && $result['fail_count'] > 0) {
            foreach ($result['errors'] as $error) {
                $this->dispatcher->dispatch(new AhaSendDeliveryEvent($error));
            }
        }

        return $response;
    }

    /**
     * @param Address[] $addresses
     *
     * @return list<array{email: string, name?: string}>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        // "From" and "Subject" headers are handled by the message itself
        $payload = [
            'recipients' => $this->formatAddresses($envelope->getRecipients()),
            'from' => $this->formatAddress($envelope->getSender()),
            'subject' => $email->getSubject(),
        ];

        if ($text = $email->getTextBody()) {
            $payload['text_content'] = $text;
        }
        if ($html = $email->getHtmlBody()) {
            $payload['html_content'] = $html;
        }
        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->formatAddress(array_pop($replyTo));
        }

        [$headers, $tags] = $this->prepareHeaders($email->getHeaders());
        $tracking = TrackingHeader::fromHeaders($email->getHeaders());
        if ($headers) {
            $payload['headers'] = $headers;
        }
        if ($tags) {
            $payload['tags'] = $tags;
        }
        if (null !== $tracking?->getOpens()) {
            $payload['tracking']['open'] = $tracking->getOpens();
        }
        if (null !== $tracking?->getClicks()) {
            $payload['tracking']['click'] = $tracking->getClicks();
        }

        if ($email->getAttachments()) {
            $payload['attachments'] = $this->getAttachments($email);
        }

        return $payload;
    }

    private function getLegacyPayload(Email $email, Envelope $envelope): array
    {
        // "From" and "Subject" headers are handled by the message itself
        $payload = [
            'recipients' => $this->formatAddresses($envelope->getRecipients()),
            'from' => $this->formatAddress($envelope->getSender()),
            'content' => [
                'subject' => $email->getSubject(),
            ],
        ];

        if ($text = $email->getTextBody()) {
            $payload['content']['text_body'] = $text;
        }
        if ($html = $email->getHtmlBody()) {
            $payload['content']['html_body'] = $html;
        }
        if ($replyTo = $email->getReplyTo()) {
            $payload['content']['reply_to'] = $this->formatAddress(array_pop($replyTo));
        }

        [$headers, $tags] = $this->prepareHeaders($email->getHeaders());
        $tracking = TrackingHeader::fromHeaders($email->getHeaders());
        if ($tags) {
            $tagsStr = implode(',', $tags);
            $email->getHeaders()->addTextHeader('AhaSend-Tags', $tagsStr);
            $headers['AhaSend-Tags'] = $tagsStr;
        }
        // the v1 API reads tracking settings from the message headers, not from a payload field
        if (null !== $tracking?->getOpens() && !$email->getHeaders()->has('AhaSend-Track-Opens')) {
            $headers['AhaSend-Track-Opens'] = $tracking->getOpens() ? 'true' : 'false';
        }
        if (null !== $tracking?->getClicks() && !$email->getHeaders()->has('AhaSend-Track-Clicks')) {
            $headers['AhaSend-Track-Clicks'] = $tracking->getClicks() ? 'true' : 'false';
        }
        if ($headers) {
            $payload['content']['headers'] = $headers;
        }

        if ($email->getAttachments()) {
            $payload['content']['attachments'] = $this->getAttachments($email);
        }

        return $payload;
    }

    /**
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function prepareHeaders(Headers $headers): array
    {
        $headersPrepared = [];
        // AhaSend API does not accept these headers.
        $headersToBypass = ['To', 'From', 'Subject', 'Reply-To'];
        $tags = [];
        foreach ($headers->all() as $header) {
            if (\in_array($header->getName(), $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
                continue;
            }

            if (0 === strcasecmp($header->getName(), TrackingHeader::NAME)) {
                continue;
            }

            $headersPrepared[$header->getName()] = $header->getBodyAsString();
        }

        return [$headersPrepared, $tags];
    }

    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $contentType = $headers->get('Content-Type')->getBody();
            $base64 = 'text/plain' !== $contentType;
            $disposition = $headers->getHeaderBody('Content-Disposition');

            if ($base64) {
                $body = base64_encode($attachment->getBody());
            } else {
                $body = $attachment->getBody();
            }

            $att = [
                'content_type' => $contentType,
                'file_name' => $attachment->getFilename(),
                'data' => $body,
                'base64' => $base64,
            ];

            if ($attachment->hasContentId()) {
                $att['content_id'] = $attachment->getContentId();
            } elseif ('inline' === $disposition) {
                $att['content_id'] = $attachment->getFilename();
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function formatAddress(Address $address): array
    {
        $formattedAddress = ['email' => $address->getEncodedAddress()];

        if ($address->getName()) {
            $formattedAddress['name'] = $address->getName();
        }

        return $formattedAddress;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: ($this->legacy ? self::LEGACY_HOST : self::HOST)).($this->port ? ':'.$this->port : '');
    }
}
