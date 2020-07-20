<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MailgunApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.%region_dot%mailgun.net';

    private $key;
    private $domain;
    private $region;

    public function __construct(string $key, string $domain, string $region = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->domain = $domain;
        $this->region = $region;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mailgun+api://%s?domain=%s', $this->getEndpoint(), $this->domain);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $body = new FormDataPart($this->getPayload($email, $envelope));
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }

        $endpoint = sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->domain));
        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        $result = $response->toArray(false);
        if (200 !== $response->getStatusCode()) {
            if ('application/json' === $response->getHeaders(false)['content-type'][0]) {
                throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $response->getStatusCode()), $response);
            }

            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $response->getStatusCode()), $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $headers = $email->getHeaders();
        $html = $email->getHtmlBody();
        if (null !== $html && \is_resource($html)) {
            if (stream_get_meta_data($html)['seekable'] ?? false) {
                rewind($html);
            }
            $html = stream_get_contents($html);
        }
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);

        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => implode(',', $this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
            'attachment' => $attachments,
            'inline' => $inlines,
        ];
        if ($emails = $email->getCc()) {
            $payload['cc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = implode(',', $this->stringifyAddresses($emails));
        }
        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }
        if ($html) {
            $payload['html'] = $html;
        }

        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type'];
        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload['o:tag'] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['v:'.$header->getKey()] = $header->getValue();

                continue;
            }

            // Check if it is a valid prefix or header name according to Mailgun API
            $prefix = substr($name, 0, 2);
            if (\in_array($prefix, ['h:', 't:', 'o:', 'v:']) || \in_array($name, ['recipient-variables', 'template', 'amp-html'])) {
                $headerName = $name;
            } else {
                // fallback to prefix with "h:" to not break BC
                $headerName = 'h:'.$name;
                @trigger_error(sprintf('Not prefixing the Mailgun header name with "h:" is deprecated since Symfony  5.1. Use header name "%s" instead.', $headerName), E_USER_DEPRECATED);
            }

            $payload[$headerName] = $header->getBodyAsString();
        }

        return $payload;
    }

    private function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = $inlines = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                // replace the cid with just a file name (the only supported way by Mailgun)
                if ($html) {
                    $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
                    $new = basename($filename);
                    $html = str_replace('cid:'.$filename, 'cid:'.$new, $html);
                    $p = new \ReflectionProperty($attachment, 'filename');
                    $p->setAccessible(true);
                    $p->setValue($attachment, $new);
                }
                $inlines[] = $attachment;
            } else {
                $attachments[] = $attachment;
            }
        }

        return [$attachments, $inlines, $html];
    }

    private function getEndpoint(): ?string
    {
        $host = $this->host ?: str_replace('%region_dot%', 'us' !== ($this->region ?: 'us') ? $this->region.'.' : '', self::HOST);

        return $host.($this->port ? ':'.$this->port : '');
    }
}
