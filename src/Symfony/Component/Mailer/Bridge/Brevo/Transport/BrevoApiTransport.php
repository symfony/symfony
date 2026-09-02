<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Pierre TANGUY
 */
final class BrevoApiTransport extends AbstractApiTransport implements RemoteTemplateTransportInterface
{
    public function __construct(
        #[\SensitiveParameter] private string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('brevo+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v3/smtp/email', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'api-key' => $this->key,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Brevo server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.($result['message'] ?? $response->getContent(false)).\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['messageId']);

        return $response;
    }

    /**
     * @return list<array{email: string, name?: string, contactPixelTrackingConsent?: bool}>
     */
    private function formatAddresses(array $addresses, ?bool $tracking = null): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $address) {
            $formattedAddresses[] = $this->formatAddress($address, $tracking);
        }

        return $formattedAddresses;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $tracking = $this->getTracking($email->getHeaders());
        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;

        $payload = [
            'sender' => $this->formatAddress($envelope->getSender()),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope), $tracking),
        ];
        if (null === $template || null !== $email->getSubject()) {
            $payload['subject'] = $email->getSubject();
        }
        if (null !== $template) {
            if (!ctype_digit($template->getReference())) {
                throw new InvalidArgumentException(\sprintf('The Brevo API expects a numeric template id, "%s" given.', $template->getReference()));
            }
            $payload['templateId'] = (int) $template->getReference();
            if ($template->getVariables()) {
                $payload['params'] = $template->getVariables();
            }
        }
        if ($attachments = $this->prepareAttachments($email)) {
            $payload['attachment'] = $attachments;
        }
        if ($emails = $email->getReplyTo()) {
            $payload['replyTo'] = current($this->formatAddresses($emails));
        }
        if ($emails = $email->getCc()) {
            $payload['cc'] = $this->formatAddresses($emails, $tracking);
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = $this->formatAddresses($emails, $tracking);
        }
        if ($email->getTextBody()) {
            $payload['textContent'] = $email->getTextBody();
        }
        if ($email->getHtmlBody()) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($headersAndTags = $this->prepareHeadersAndTags($email->getHeaders())) {
            $payload = array_merge($payload, $headersAndTags);
        }

        return $payload;
    }

    private function prepareAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $att = [
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
                'name' => $filename,
            ];

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function prepareHeadersAndTags(Headers $headers): array
    {
        $headersAndTags = [];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, ['from', 'sender', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'accept', 'api-key'], true)) {
                continue;
            }
            if ($header instanceof TagHeader) {
                $headersAndTags['tags'][] = $header->getValue();

                continue;
            }
            if ($header instanceof MetadataHeader) {
                $headersAndTags['headers']['X-Mailin-'.ucfirst(strtolower($header->getKey()))] = $header->getValue();

                continue;
            }
            if ('templateid' === $name) {
                trigger_deprecation('symfony/brevo-mailer', '8.2', 'Using the "templateid" email header to select a Brevo template is deprecated, use a "%s" instead.', RemoteTemplateEmail::class);
                $headersAndTags[$header->getName()] = (int) $header->getValue();

                continue;
            }
            if ('params' === $name) {
                trigger_deprecation('symfony/brevo-mailer', '8.2', 'Using the "params" email header to define the variables of a Brevo template is deprecated, use a "%s" instead.', RemoteTemplateEmail::class);
                $headersAndTags[$header->getName()] = $header->getParameters();

                continue;
            }

            if (0 === strcasecmp($header->getName(), TrackingHeader::NAME)) {
                continue;
            }

            $headersAndTags['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $headersAndTags;
    }

    /**
     * Brevo only exposes a single combined "tracking consent" flag which anonymises the open/click
     * events rather than disabling them, so an explicit false on either aspect anonymises both, and
     * an explicit true on either aspect grants consent for both.
     */
    private function getTracking(Headers $headers): ?bool
    {
        $tracking = TrackingHeader::fromHeaders($headers);

        if (false === $tracking?->getOpens() || false === $tracking?->getClicks()) {
            return false;
        }

        if (true === $tracking?->getOpens() || true === $tracking?->getClicks()) {
            return true;
        }

        return null;
    }

    private function formatAddress(Address $address, ?bool $tracking = null): array
    {
        $formattedAddress = ['email' => $address->getEncodedAddress()];

        if ($address->getName()) {
            $formattedAddress['name'] = $address->getName();
        }

        if (null !== $tracking) {
            $formattedAddress['contactPixelTrackingConsent'] = $tracking;
        }

        return $formattedAddress;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.brevo.com').($this->port ? ':'.$this->port : '');
    }
}
