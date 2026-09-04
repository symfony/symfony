<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\PufferPost\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mailer\Transport\RemoteTemplateTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Jeroen Moonen (PufferPost) <info@jeroenmoonen.nl>
 */
final class PufferPostApiTransport extends AbstractApiTransport implements RemoteTemplateTransportInterface
{
    private const HOST = 'pufferpost.com';

    /**
     * Provider-specific options, set as headers so a plain Symfony Email carries them and
     * `$mailer->send()` keeps working. The transport reads them into their own payload fields and
     * skips them when building the API's header map, so they are never sent on as headers.
     */
    private const METADATA = 'x-pufferpost-metadata';
    private const UNSUBSCRIBE_GROUP = 'x-pufferpost-unsubscribe-group';
    private const LOCALE = 'x-pufferpost-locale';
    private const TIMEZONE = 'x-pufferpost-timezone';

    public function __construct(
        #[\SensitiveParameter] private readonly string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('pufferpost+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        // The API addresses one primary recipient per message, so a fan-out is submitted as a
        // batch: one request, one message per To address, each accepted or rejected on its own.
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/v1/messages/batch', [
            'auth_bearer' => $this->key,
            'json' => ['messages' => $this->getPayload($email, $envelope)],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $response->getStatusCode()), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote PufferPost server.', $response, 0, $e);
        }

        if (200 !== $statusCode && 202 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.($result['error']['message'] ?? $response->getContent(false)).\sprintf(' (code %d).', $statusCode), $response);
        }

        // A batch answers 200 even when individual messages were refused, so surface the first
        // rejection rather than reporting a send that never happened.
        $items = $result['data'] ?? [];
        foreach ($items as $item) {
            if ('accepted' !== ($item['status'] ?? null)) {
                throw new HttpTransportException('Unable to send an email: '.($item['error']['message'] ?? 'the message was rejected').\sprintf(' (code %d).', $statusCode), $response);
            }
        }

        // A fan-out produces one id per recipient; SentMessage holds a single value, so keep the
        // first and leave the rest to the batch response the caller can read.
        if (isset($items[0]['id'])) {
            $sentMessage->setMessageId((string) $items[0]['id']);
        }

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>> one message per recipient
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        // The visible sender, which the API matches against a verified sender identity. The
        // envelope sender resolves to Sender or Return-Path first, so it can be a bounce address
        // the API would refuse.
        $from = $email->getFrom();
        $shared = [
            'from' => $from ? $from[0]->getAddress() : $envelope->getSender()->getAddress(),
        ];

        // A stored template renders server side, and the API refuses a message that carries both a
        // template and inline content, so the body is sent only when no template was asked for.
        // RemoteTemplateEmail already forbids a text or HTML part alongside a template; the subject
        // is the one piece it cannot refuse, so reject it here rather than dropping it silently.
        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;
        if (null !== $template) {
            if (null !== $email->getSubject()) {
                throw new InvalidArgumentException('PufferPost does not support overriding the subject of a template; define the subject in the template itself.');
            }

            $shared['templateId'] = $template->getReference();
            if ($variables = $template->getVariables()) {
                $shared['data'] = $variables;
            }
        } else {
            if (null !== $email->getSubject()) {
                $shared['subject'] = $email->getSubject();
            }

            if (null !== $text = $email->getTextBody()) {
                $shared['text'] = $text;
            }

            if (null !== $html = $email->getHtmlBody()) {
                $shared['html'] = $html;
            }
        }

        if (null !== $metadata = $this->jsonOption($email, self::METADATA)) {
            $shared['metadata'] = $metadata;
        }

        foreach ([self::UNSUBSCRIBE_GROUP => 'unsubscribeGroup', self::LOCALE => 'locale', self::TIMEZONE => 'timezone'] as $header => $field) {
            if (null !== $value = $this->option($email, $header)) {
                $shared[$field] = $value;
            }
        }

        // The API carries a single reply-to; the rest cannot be passed as a raw Reply-To header
        // because the headers map is allow-listed to X- names and would refuse the message.
        if ($replyTo = $email->getReplyTo()) {
            $shared['replyTo'] = $replyTo[0]->getAddress();
        }

        if ($attachments = $this->getAttachments($email)) {
            $shared['attachments'] = $attachments;
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            // Only custom X- headers; anything else is either carried by a payload field or
            // rejected by the API. The provider's own options are consumed above.
            if (!str_starts_with($name, 'x-') || str_starts_with($name, 'x-pufferpost-')) {
                continue;
            }

            $shared['headers'][$header->getName()] = $header->getBodyAsString();
        }

        // The envelope is the delivery truth: framework.mailer.envelope.recipients rewrites it and
        // not the headers, so honouring the headers here would ignore a staging redirect and mail
        // real customers. Cc and Bcc ride on the first message only, because each item is an
        // independent email and repeating them would deliver a copy per recipient.
        $recipients = $this->getRecipients($email, $envelope);
        $cc = $this->addressList($email->getCc(), $envelope);
        $bcc = $this->addressList($email->getBcc(), $envelope);
        if (!$recipients) {
            // Nothing but Cc/Bcc: every envelope recipient becomes its own message instead, so the
            // send is not silently empty.
            $recipients = $envelope->getRecipients();
            $cc = $bcc = [];
        }

        $messages = [];
        foreach ($recipients as $index => $recipient) {
            $message = ['to' => $recipient->getAddress()] + $shared;
            if (0 === $index) {
                if ($cc) {
                    $message['cc'] = $cc;
                }
                if ($bcc) {
                    $message['bcc'] = $bcc;
                }
            }
            $messages[] = $message;
        }

        return $messages;
    }

    private function option(Email $email, string $name): ?string
    {
        $body = $email->getHeaders()->getHeaderBody($name);

        return \is_string($body) && '' !== $body ? $body : null;
    }

    /**
     * A JSON-valued option. A malformed value is an error rather than a silent drop: sending the
     * message without its render variables would deliver a half-rendered template.
     *
     * @return array<array-key, mixed>|null
     */
    private function jsonOption(Email $email, string $name): ?array
    {
        if (null === $raw = $this->option($email, $name)) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            throw new TransportException(\sprintf('The "%s" header must contain a JSON object.', $name));
        }

        return $decoded;
    }

    /**
     * Cc/Bcc addresses, dropped when the envelope no longer carries them, so a redirected envelope
     * does not keep mailing the original copy recipients.
     *
     * @param Address[] $addresses
     *
     * @return list<string>
     */
    private function addressList(array $addresses, Envelope $envelope): array
    {
        $allowed = array_map(static fn (Address $a): string => $a->getAddress(), $envelope->getRecipients());

        $list = [];
        foreach ($addresses as $address) {
            if (\in_array($address->getAddress(), $allowed, true)) {
                $list[] = $address->getAddress();
            }
        }

        return $list;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            // The API has no Content-ID support, so an inline part would be delivered as a plain
            // attachment and its cid: reference would break silently. Fail loudly instead.
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                throw new TransportException('PufferPost does not support inline (cid-embedded) attachments; attach the file or host the image at a URL instead.');
            }

            $attachments[] = [
                'filename' => $headers->getHeaderParameter('Content-Disposition', 'filename'),
                'contentType' => $headers->get('Content-Type')->getBody(),
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
