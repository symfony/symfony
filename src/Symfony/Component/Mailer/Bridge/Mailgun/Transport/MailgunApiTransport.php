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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MailgunApiTransport extends AbstractApiTransport implements RemoteTemplateTransportInterface
{
    private const HOST = 'api.%region_dot%mailgun.net';

    public function __construct(
        #[\SensitiveParameter] private string $key,
        private string $domain,
        private ?string $region = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('mailgun+api://%s?domain=%s', $this->getEndpoint(), $this->domain);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $body = new FormDataPart($this->getPayload($email, $envelope));
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }

        $endpoint = \sprintf('%s/v3/%s/messages', $this->getEndpoint(), urlencode($this->domain));
        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'http_version' => '1.1',
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mailgun server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].\sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $headers = $email->getHeaders();
        $headers->addMailboxHeader('h:Sender', $envelope->getSender());
        $html = $email->getHtmlBody();
        if (null !== $html && \is_resource($html)) {
            if (stream_get_meta_data($html)['seekable'] ?? false) {
                rewind($html);
            }
            $html = stream_get_contents($html);
        }
        [$attachments, $inlines, $html] = $this->prepareAttachments($email, $html);
        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;

        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => implode(',', $this->stringifyAddresses($this->getRecipients($email, $envelope))),
        ];
        if (null === $template || null !== $email->getSubject()) {
            $payload['subject'] = $email->getSubject();
        }
        $payload['attachment'] = $attachments;
        $payload['inline'] = $inlines;
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

        // resolve the generic header first, so a native o:/h:/t:/v: header always wins regardless of iteration order
        if ($tracking = TrackingHeader::fromHeaders($headers)) {
            if (null !== $tracking->getOpens()) {
                $payload['o:tracking-opens'] = $tracking->getOpens() ? 'yes' : 'no';
            }
            if (null !== $tracking->getClicks()) {
                $payload['o:tracking-clicks'] = $tracking->getClicks() ? 'yes' : 'no';
            }
        }

        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, ['from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'x-track'], true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload[] = ['o:tag' => $header->getValue()];

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['v:'.$header->getKey()] = $header->getValue();

                continue;
            }

            // Check if it is a valid prefix or header name according to Mailgun API
            $prefix = substr($name, 0, 2);
            if (\in_array($prefix, ['h:', 't:', 'o:', 'v:']) || \in_array($name, ['recipient-variables', 'template', 'amp-html'], true)) {
                if ('template' === $name) {
                    trigger_deprecation('symfony/mailgun-mailer', '8.2', 'Using the "template" email header to select a Mailgun template is deprecated, use a "%s" instead.', RemoteTemplateEmail::class);
                }
                $headerName = $header->getName();
            } else {
                $headerName = 'h:'.$header->getName();
            }

            $payload[$headerName] = $header->getBodyAsString();
        }

        if (null !== $template) {
            $payload['template'] = $template->getReference();
            if ($template->getVariables()) {
                $payload['t:variables'] = json_encode($template->getVariables(), \JSON_THROW_ON_ERROR);
            }
        }

        return $payload;
    }

    private function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = $inlines = $replacements = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                // replace the cid with just a file name (the only supported way by Mailgun)
                if ($html) {
                    $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
                    $new = basename($filename);
                    $replacements['cid:'.$filename] = 'cid:'.$new;
                    $p = new \ReflectionProperty($attachment, 'filename');
                    $p->setValue($attachment, $new);
                }
                $inlines[] = $attachment;
            } else {
                $attachments[] = $attachment;
            }
        }

        // strtr() replaces the longest match first and never rewrites what it already replaced
        return [$attachments, $inlines, $replacements ? strtr($html, $replacements) : $html];
    }

    private function getEndpoint(): ?string
    {
        $host = $this->host ?: str_replace('%region_dot%', 'us' !== ($this->region ?: 'us') ? $this->region.'.' : '', self::HOST);

        return $host.($this->port ? ':'.$this->port : '');
    }
}
