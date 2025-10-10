<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\TokenManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MicrosoftGraphApiTransport extends AbstractApiTransport
{
    private const USER_ENDPOINT = '%s/v1.0/users/%s/sendMail';

    /**
     * @param string $graphEndpoint Graph API URL to which to POST emails
     * @param bool   $noSave        Whether the skip saving the send message in the Sent Items box
     */
    public function __construct(
        private readonly string $graphEndpoint,
        private readonly TokenManager $tokenManager,
        private readonly bool $noSave,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('microsoftgraph+api://%s', $this->graphEndpoint);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $endpoint = \sprintf('https://'.self::USER_ENDPOINT, $this->graphEndpoint, $envelope->getSender()->getAddress());
        $payload = $this->getPayload($email, $envelope);

        $response = $this->client->request('POST', $endpoint, [
            'json' => $payload,
            'auth_bearer' => $this->tokenManager->getToken(),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Microsoft server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $message = [
            'sender' => $this->getEmailAddress($envelope->getSender()),
            'subject' => $email->getSubject(),
            'body' => $this->getBodyPayload($email),
            'importance' => $this->getImportanceLevel($email),
            'toRecipients' => array_map($this->getEmailAddress(...), $email->getTo()),
        ];

        if ($email->getFrom()) {
            // Microsoft only supports a single from
            $message['from'] = $this->getEmailAddress($email->getFrom()[0]);
        }

        if ($attachments = $this->getMessageAttachments($email)) {
            $message['attachments'] = $attachments;
        }

        if ($bcc = array_map($this->getEmailAddress(...), $email->getBcc())) {
            $message['bccRecipients'] = $bcc;
        }

        if ($cc = array_map($this->getEmailAddress(...), $email->getCc())) {
            $message['ccRecipients'] = $cc;
        }

        if ($headers = $this->getMessageCustomHeaders($email)) {
            $message['internetMessageHeaders'] = $headers;
        }

        if ($replyTo = array_map($this->getEmailAddress(...), $email->getReplyTo())) {
            $message['replyTo'] = $replyTo;
        }

        $data['message'] = $message;
        if ($this->noSave) {
            $data['saveToSentItems'] = false;
        }

        return $data;
    }

    private function getBodyPayload(Email $email): array
    {
        // Microsoft message can either be HTML or text, but not both
        if ($email->getHtmlBody()) {
            return [
                'content' => $email->getHtmlBody(),
                'contentType' => 'html',
            ];
        }

        return [
            'content' => $email->getTextBody(),
            'contentType' => 'text',
        ];
    }

    private function getEmailAddress(Address $address): array
    {
        $data = ['address' => $address->getAddress()];

        if ($address->getName()) {
            $data['name'] = $address->getName();
        }

        return ['emailAddress' => $data];
    }

    private function getMessageAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $attr = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $filename,
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentType' => $headers->get('Content-Type')->getBody(),
            ];

            if ('inline' === $disposition) {
                $attr['contentId'] = $filename;
                $attr['isInline'] = true;
            }

            $attachments[] = $attr;
        }

        return $attachments;
    }

    private function getMessageCustomHeaders(Email $email): array
    {
        $headers = [];

        $headersToBypass = ['x-ms-client-request-id', 'operation-id', 'authorization', 'x-ms-content-sha256', 'received', 'dkim-signature', 'content-transfer-encoding', 'from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'reply-to'];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }
            $headers[] = [
                'name' => $header->getName(),
                'value' => $header->getBodyAsString(),
            ];
        }

        return $headers;
    }

    private function getImportanceLevel(Email $email): string
    {
        return match ($email->getPriority()) {
            Email::PRIORITY_HIGHEST,
            Email::PRIORITY_HIGH => 'high',
            Email::PRIORITY_LOW,
            Email::PRIORITY_LOWEST => 'low',
            default => 'normal',
        };
    }
}
