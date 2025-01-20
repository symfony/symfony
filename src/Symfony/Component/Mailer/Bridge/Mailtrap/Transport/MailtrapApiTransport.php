<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class MailtrapApiTransport extends AbstractApiTransport
{
    private const HOST = 'send.api.mailtrap.io';
    private const HEADERS_TO_BYPASS = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'sender'];

    public function __construct(
        #[\SensitiveParameter] private string $token,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('mailtrap+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/send', [
            'json' => $this->getPayload($email, $envelope),
            'auth_bearer' => $this->token,
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mailtrap server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException(\sprintf('Unable to send email: "%s" (status code %d).', implode(', ', $result['errors']), $statusCode), $response);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => self::encodeEmail($envelope->getSender()),
            'to' => array_map(self::encodeEmail(...), $email->getTo()),
            'cc' => array_map(self::encodeEmail(...), $email->getCc()),
            'bcc' => array_map(self::encodeEmail(...), $email->getBcc()),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'attachments' => $this->getAttachments($email),
        ];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, self::HEADERS_TO_BYPASS, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                if (isset($payload['category'])) {
                    throw new TransportException('Mailtrap only allows a single category per email.');
                }

                $payload['category'] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['custom_variables'][$header->getKey()] = $header->getValue();

                continue;
            }

            $payload['headers'][$header->getName()] = $header->getBodyAsString();
        }

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
                'type' => $headers->get('Content-Type')->getBody(),
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

    private static function encodeEmail(Address $address): array
    {
        return array_filter(['email' => $address->getEncodedAddress(), 'name' => $address->getName()]);
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
