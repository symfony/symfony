<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Dominik Spitzli <dominik@spitzli.dev>
 */
final class TurboSmtpApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.turbo-smtp.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        #[\SensitiveParameter] private readonly string $secret,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('turbosmtp+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/v2/mail/send', [
            'headers' => [
                'consumerKey' => $this->key,
                'consumerSecret' => $this->secret,
            ],
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote TurboSMTP server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $errors = $result['errors'] ?? null;
            $reason = \is_array($errors) ? implode(', ', $errors) : ($errors ?? $result['message'] ?? $response->getContent(false));

            throw new HttpTransportException('Unable to send an email: '.$reason.\sprintf(' (code %d).', $statusCode), $response);
        }

        if (isset($result['mid'])) {
            $sentMessage->setMessageId((string) $result['mid']);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $envelope->getSender()->toString(),
            'to' => implode(',', $this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
        ];

        if ($text = $email->getTextBody()) {
            $payload['content'] = $text;
        }

        if ($html = $email->getHtmlBody()) {
            $payload['html_content'] = $html;
        }

        if ($cc = $email->getCc()) {
            $payload['cc'] = implode(',', $this->stringifyAddresses($cc));
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = implode(',', $this->stringifyAddresses($bcc));
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['custom_headers']['Reply-To'] = implode(',', $this->stringifyAddresses($replyTo));
        }

        if ($attachments = $this->getAttachments($email)) {
            $payload['attachments'] = $attachments;

            if (isset($payload['html_content'])) {
                $payload['html_content'] = $this->qualifyInlineCids($payload['html_content'], $attachments, $envelope->getSender()->getAddress());
            }
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'content-transfer-encoding', 'mime-version', 'dkim-signature', 'received', 'message-id', 'date'], true)) {
                continue;
            }

            $payload['custom_headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
    }

    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $item = [
                'name' => $filename,
                'type' => $headers->get('Content-Type')->getBody(),
                'content' => base64_encode($attachment->getBody()),
            ];

            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                $item['content_id'] = $attachment->hasContentId() ? $attachment->getContentId() : $filename;
            }

            $attachments[] = $item;
        }

        return $attachments;
    }

    /**
     * Qualifies inline `cid:` references with the sender domain.
     *
     * TurboSMTP builds each inline part's Content-ID as `<content_id@sender-domain>`, so a bare
     * `cid:the-id` reference in the HTML only resolves once it carries the same domain. Otherwise
     * the image is delivered as a normal attachment instead of rendering inline.
     */
    private function qualifyInlineCids(string $html, array $attachments, string $sender): string
    {
        $domain = substr(strrchr($sender, '@') ?: '@', 1);
        if ('' === $domain) {
            return $html;
        }

        foreach ($attachments as $attachment) {
            $cid = $attachment['content_id'] ?? null;
            if (null === $cid || str_contains($cid, '@')) {
                continue;
            }

            $html = preg_replace('/cid:'.preg_quote($cid, '/').'(?![\w.@-])/', 'cid:'.$cid.'@'.$domain, $html) ?? $html;
        }

        return $html;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
