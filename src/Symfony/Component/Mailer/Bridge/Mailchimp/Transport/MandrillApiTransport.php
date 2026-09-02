<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Transport;

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
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MandrillApiTransport extends AbstractApiTransport implements RemoteTemplateTransportInterface
{
    private const HOST = 'mandrillapp.com';

    public function __construct(
        #[\SensitiveParameter] private string $key,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('mandrill+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $endpoint = $email instanceof RemoteTemplateEmail && null !== $email->getRemoteTemplate() ? 'send-template' : 'send';
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/1.0/messages/'.$endpoint.'.json', [
            'json' => $this->getPayload($email, $envelope),
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mandrill server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            if ('error' === ($result['status'] ?? false)) {
                throw new HttpTransportException('Unable to send an email: '.$result['message'].\sprintf(' (code %d).', $result['code']), $response);
            }

            throw new HttpTransportException(\sprintf('Unable to send an email (code %d).', $result['code']), $response);
        }

        $firstRecipient = reset($result);
        $sentMessage->setMessageId($firstRecipient['_id']);

        return $response;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $template = $email instanceof RemoteTemplateEmail ? $email->getRemoteTemplate() : null;

        $payload = [
            'key' => $this->key,
            'message' => [
                'html' => $email->getHtmlBody(),
                'text' => $email->getTextBody(),
            ],
        ];
        if (null === $template || null !== $email->getSubject()) {
            $payload['message']['subject'] = $email->getSubject();
        }
        $payload['message']['from_email'] = $envelope->getSender()->getAddress();
        $payload['message']['to'] = $this->getRecipientsPayload($email, $envelope);

        if (null !== $template) {
            $payload['template_name'] = $template->getReference();
            $payload['template_content'] = [];
            foreach ($template->getVariables() as $name => $content) {
                $payload['message']['global_merge_vars'][] = ['name' => $name, 'content' => $content];
            }
        }

        if ($email->getHeaders()->get('X-MC-Subaccount')) {
            $payload['message']['subaccount'] = $email->getHeaders()->get('X-MC-Subaccount')->getBodyAsString();
        }

        if ($email->getHeaders()->get('X-MC-ReturnPathDomain')) {
            $payload['message']['return_path_domain'] = $email->getHeaders()->get('X-MC-ReturnPathDomain')->getBodyAsString();
        }

        if ('' !== $envelope->getSender()->getName()) {
            $payload['message']['from_name'] = $envelope->getSender()->getName();
        }

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'content' => $attachment->bodyToString(),
                'type' => $headers->get('Content-Type')->getBody(),
            ];

            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $att['name'] = $name;
            }

            if ('inline' === $disposition) {
                if ($attachment->hasContentId()) {
                    $att['name'] = $attachment->getContentId();
                }
                $payload['message']['images'][] = $att;
            } else {
                $payload['message']['attachments'][] = $att;
            }
        }

        if ($tracking = TrackingHeader::fromHeaders($email->getHeaders())) {
            if (null !== $tracking->getOpens()) {
                $payload['message']['track_opens'] = $tracking->getOpens();
            }
            if (null !== $tracking->getClicks()) {
                $payload['message']['track_clicks'] = $tracking->getClicks();
            }
        }

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, ['from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'x-mc-subaccount', 'x-mc-returnpathdomain', 'x-track'], true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $payload['message']['tags'] = array_merge(
                    $payload['message']['tags'] ?? [],
                    explode(',', $header->getValue())
                );

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $payload['message']['metadata'][$header->getKey()] = $header->getValue();

                continue;
            }

            $payload['message']['headers'][$header->getName()] = $header->getBodyAsString();
        }

        return $payload;
    }

    private function getRecipientsPayload(Email $email, Envelope $envelope): array
    {
        $recipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';
            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipientPayload = [
                'email' => $recipient->getAddress(),
                'type' => $type,
            ];

            if ('' !== $recipient->getName()) {
                $recipientPayload['name'] = $recipient->getName();
            }

            $recipients[] = $recipientPayload;
        }

        return $recipients;
    }
}
