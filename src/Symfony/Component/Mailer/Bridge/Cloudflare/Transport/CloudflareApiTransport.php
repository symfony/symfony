<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author vadage
 */
final class CloudflareApiTransport extends AbstractApiTransport
{
    private const string HOST = 'api.cloudflare.com';

    public function __construct(
        private readonly string $accountId,
        #[\SensitiveParameter] private readonly string $apiToken,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('cloudflare+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $path = \sprintf('/client/v4/accounts/%1$s/email/sending/send', $this->accountId);

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().$path, [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiToken,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Cloudflare server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            try {
                $result = $response->toArray(false);

                $errorMessages = implode('; ', array_column($result['errors'], 'message'));
                $message = \sprintf('Unable to send an email: %1$s (code: %2$d)', $errorMessages, $statusCode);
                throw new HttpTransportException($message, $response);
            } catch (DecodingExceptionInterface $e) {
                $message = \sprintf('Unable to send an email: %1$s (code: %2$d)', $response->getContent(false), $statusCode);
                throw new HttpTransportException($message, $response, 0, $e);
            }
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'subject' => $email->getSubject(),
            'to' => array_map($this->formatAddress(...), $this->getRecipients($email, $envelope)),
        ];

        if ($headers = $this->prepareHeaders($email->getHeaders())) {
            $payload['headers'] = $headers;
        }

        if ($cc = array_map($this->formatAddress(...), $email->getCc())) {
            $payload['cc'] = $cc;
        }

        if ($bcc = array_map($this->formatAddress(...), $email->getBcc())) {
            $payload['bcc'] = $bcc;
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->formatAddress($replyTo[0]);
        }

        $htmlBody = $email->getHtmlBody();
        if (\is_string($htmlBody)) {
            $payload['html'] = $htmlBody;
        }

        $textBody = $email->getTextBody();
        if (\is_string($textBody)) {
            $payload['text'] = $textBody;
        }

        if ($attachments = array_map($this->formatAttachment(...), $email->getAttachments())) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    private function formatAddress(Address $address): array
    {
        return [
            'address' => $address->getAddress(),
            'name' => $address->getName(),
        ];
    }

    private function formatAttachment(DataPart $attachment): array
    {
        $disposition = $attachment->getDisposition();
        $formattedPart = [
            'content' => base64_encode($attachment->getBody()),
            'disposition' => $disposition,
            'filename' => $attachment->getFilename(),
            'type' => $attachment->getContentType(),
        ];

        if ('inline' === $disposition) {
            $formattedPart['content_id'] = $attachment->getContentId();
        }

        return $formattedPart;
    }

    private function prepareHeaders(Headers $headers): array
    {
        $headersToBypass = ['From', 'Subject', 'To', 'Bcc', 'Cc', 'Reply-To'];

        $preparedHeaders = [];
        foreach ($headers->all() as $header) {
            if (\in_array($header->getName(), $headersToBypass, true)) {
                continue;
            }

            $preparedHeaders[$header->getName()] = $header->getBodyAsString();
        }

        return $preparedHeaders;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
