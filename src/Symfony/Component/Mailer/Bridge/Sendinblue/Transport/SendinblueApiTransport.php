<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendinblue\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
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
 * @author Yann LUCAS
 */
final class SendinblueApiTransport extends AbstractApiTransport
{
    private string $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('sendinblue+api://%s', $this->getEndpoint());
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
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Sendinblue server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['messageId']);

        return $response;
    }

    protected function stringifyAddresses(array $addresses): array
    {
        $stringifiedAddresses = [];
        foreach ($addresses as $address) {
            $stringifiedAddresses[] = $this->stringifyAddress($address);
        }

        return $stringifiedAddresses;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'sender' => $this->stringifyAddress($envelope->getSender()),
            'to' => $this->stringifyAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];
        if ($attachements = $this->prepareAttachments($email)) {
            $payload['attachment'] = $attachements;
        }
        if ($emails = $email->getReplyTo()) {
            $payload['replyTo'] = current($this->stringifyAddresses($emails));
        }
        if ($emails = $email->getCc()) {
            $payload['cc'] = $this->stringifyAddresses($emails);
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = $this->stringifyAddresses($emails);
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
        $headersToBypass = ['from', 'sender', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'accept', 'api-key'];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
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
                $headersAndTags[$header->getName()] = (int) $header->getValue();

                continue;
            }
            if ('params' === $name) {
                $headersAndTags[$header->getName()] = $header->getParameters();

                continue;
            }
            $headersAndTags['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $headersAndTags;
    }

    private function stringifyAddress(Address $address): array
    {
        $stringifiedAddress = ['email' => $address->getAddress()];

        if ($address->getName()) {
            $stringifiedAddress['name'] = $address->getName();
        }

        return $stringifiedAddress;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.sendinblue.com').($this->port ? ':'.$this->port : '');
    }
}
