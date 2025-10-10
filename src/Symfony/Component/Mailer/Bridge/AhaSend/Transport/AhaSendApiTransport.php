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
 * @author Farhad Hedayatifard <farhad@ahasend.com>
 */
final class AhaSendApiTransport extends AbstractApiTransport
{
    private const HOST = 'send.ahasend.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        ?HttpClientInterface $client = null,
        private ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('ahasend+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1/email/send', [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'X-Api-Key' => $this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote AhaSend server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        if ($result['fail_count'] > 0) {
            if (null !== $this->dispatcher) {
                foreach ($result['errors'] as $error) {
                    $this->dispatcher->dispatch(new AhaSendDeliveryEvent($error));
                }
            }
        }

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
            'recipients' => $this->formatAddresses($envelope->getRecipients()),
            'from' => $this->formatAddress($envelope->getSender()),
            'content' => [
                'subject' => $email->getSubject(),
            ],
        ];

        $text = $email->getTextBody();
        if (!empty($text)) {
            $payload['content']['text_body'] = $text;
        }
        $html = $email->getHtmlBody();
        if (!empty($html)) {
            $payload['content']['html_body'] = $html;
        }

        $replyTo = $email->getReplyTo();
        if ($replyTo) {
            $replyTo = array_pop($replyTo);
            $payload['content']['reply_to'] = $this->formatAddress($replyTo);
        }

        $headers = $this->prepareHeaders($email->getHeaders());
        if (!empty($headers)) {
            $payload['content']['headers'] = $headers;
        }

        if ($email->getAttachments()) {
            $payload['content']['attachments'] = $this->getAttachments($email);
        }

        return $payload;
    }

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
                $headers->remove($header->getName());
                continue;
            }

            $headersPrepared[$header->getName()] = $header->getBodyAsString();
        }
        if (!empty($tags)) {
            $tagsStr = implode(',', $tags);
            $headers->addTextHeader('AhaSend-Tags', $tagsStr);
            $headersPrepared['AhaSend-Tags'] = $tagsStr;
        }

        return $headersPrepared;
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
                'content_type' => $headers->get('Content-Type')->getBody(),
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

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
