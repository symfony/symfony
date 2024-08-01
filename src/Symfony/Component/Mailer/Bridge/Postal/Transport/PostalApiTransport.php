<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postal\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PostalApiTransport extends AbstractApiTransport
{
    public function __construct(
        #[\SensitiveParameter] private string $apiToken,
        private string $hostName,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
        $this->setHost($hostName);
    }

    public function __toString(): string
    {
        return \sprintf('postal+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $path = \sprintf('/api/v1/send/message');

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().$path, [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'X-Server-API-Key' => $this->apiToken,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Postal server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['message_id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $envelope->getSender()->getAddress(),
            'to' => array_map(fn (Address $address) => $address->getAddress(), $this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];
        if ($emails = $email->getCc()) {
            $payload['cc'] = array_map(fn (Address $address) => $address->getAddress(), $emails);
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = array_map(fn (Address $address) => $address->getAddress(), $emails);
        }
        if ($email->getTextBody()) {
            $payload['plain_body'] = $email->getTextBody();
        }
        if ($email->getHtmlBody()) {
            $payload['html_body'] = $email->getHtmlBody();
        }
        if ($attachments = $this->prepareAttachments($email)) {
            $payload['attachments'] = $attachments;
        }
        if ($headers = $this->getCustomHeaders($email)) {
            $payload['headers'] = $headers;
        }
        if ($emails = $email->getReplyTo()) {
            $payload['reply_to'] = $emails[0]->getAddress();
        }

        return $payload;
    }

    private function prepareAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'name' => $attachment->getFilename(),
                'content_type' => $attachment->getContentType(),
                'data' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    private function getCustomHeaders(Email $email): array
    {
        $headers = [];
        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'sender', 'reply-to'];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            $headers[] = [
                'key' => $header->getName(),
                'value' => $header->getBodyAsString(),
            ];
        }

        return $headers;
    }

    private function getEndpoint(): string
    {
        return $this->host.($this->port ? ':'.$this->port : '');
    }
}
