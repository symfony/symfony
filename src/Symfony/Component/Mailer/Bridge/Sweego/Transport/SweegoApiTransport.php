<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
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
final class SweegoApiTransport extends AbstractApiTransport
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
        return \sprintf('sweego+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/send', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Api-Key' => $this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Sweego server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['transaction_id']);

        return $response;
    }

    /**
     * @param Address[] $addresses
     *
     * @return list<string>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        // "From" and "Subject" headers are handled by the message itself
        $payload = [
            'recipients' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'from' => $this->formatAddress($envelope->getSender()),
            'subject' => $email->getSubject(),
            'campaign-type' => 'transac',
            'channel' => 'email',
        ];

        if ($email->getTextBody()) {
            $payload['message-txt'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $payload['message-html'] = $email->getHtmlBody();
        }

        if ($email->getAttachments()) {
            $payload['attachments'] = $this->getAttachments($email);
        }

        if ($payload['headers'] = $this->prepareHeaders($email->getHeaders())) {
            if (\count($payload['headers']) > 5) {
                throw new InvalidArgumentException('Sweego API supports up to 5 headers.');
            }
        }

        $payload['provider'] = 'sweego';

        return $payload;
    }

    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'content' => $attachment->bodyToString(),
                'filename' => $filename,
                'disposition' => $disposition,
            ];

            if ('inline' === $disposition) {
                $att['content_id'] = $attachment->hasContentId() ? $attachment->getContentId() : $filename;
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    private function prepareHeaders(Headers $headers): array
    {
        $headersPrepared = [];
        foreach ($headers->all() as $header) {
            // Sweego API does not accept those headers.
            if (\in_array($header->getName(), ['To', 'From', 'Subject'], true)) {
                continue;
            }

            $headersPrepared[$header->getName()] = $header->getBodyAsString();
        }

        return $headersPrepared;
    }

    private function formatAddress(Address $address): array
    {
        $formattedAddress = ['email' => $address->getEncodedAddress()];

        if ($address->getName()) {
            $formattedAddress['name'] = $address->getName();
        }

        return $formattedAddress;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.sweego.io').($this->port ? ':'.$this->port : '');
    }
}
