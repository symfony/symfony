<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
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
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResendApiTransport extends AbstractApiTransport
{
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
        return \sprintf('resend+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/emails', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Resend server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    /**
     * @param Address[] $addresses
     *
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        $formattedAddresses = [];
        foreach ($addresses as $address) {
            $formattedAddresses[] = $address->getEncodedAddress();
        }

        if (\count($formattedAddresses) > 50) {
            throw new InvalidArgumentException('Resend API does not support more than 50 recipients.');
        }

        return $formattedAddresses;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];
        if ($attachements = $this->prepareAttachments($email)) {
            $payload['attachments'] = $attachements;
        }
        if ($emails = $email->getReplyTo()) {
            $payload['reply_to'] = current($this->formatAddresses($emails));
        }
        if ($emails = $email->getCc()) {
            $payload['cc'] = $this->formatAddresses($emails);
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = $this->formatAddresses($emails);
        }
        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }
        if ($email->getHtmlBody()) {
            $payload['html'] = $email->getHtmlBody();
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
            $attachments[] = [
                'filename' => $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'),
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
            ];
        }

        return $attachments;
    }

    private function prepareHeadersAndTags(Headers $headers): array
    {
        $headersAndTags = [];
        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'reply_to'];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $headersAndTags['tags'][] = [$header->getName() => $header->getValue()];

                continue;
            }

            $headersAndTags['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $headersAndTags;
    }

    private function formatAddress(Address $address): string
    {
        $formattedAddress = $address->getEncodedAddress();

        if ($address->getName()) {
            $formattedAddress = $address->getName().' <'.$formattedAddress.'>';
        }

        return $formattedAddress;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.resend.com').($this->port ? ':'.$this->port : '');
    }
}
