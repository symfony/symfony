<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Redlink\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mateusz Żyła <https://github.com/plotkabytes>
 */
final class RedlinkApiTransport extends AbstractApiTransport
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiToken,
        #[\SensitiveParameter]
        private readonly string $appToken,
        private readonly string $fromSmtp,
        private readonly ?string $version = null,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('redlink+api://%s', $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $endpoint = sprintf('https://%s/%s/email', $this->getEndpoint(), $this->version ?? 'v2.1');

        $response = $this->client->request('POST', $endpoint, [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'Authorization' => $this->apiToken,
                'Application-Key' => $this->appToken,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Redlink server.', $response, 0, $e);
        }

        $content = $response->toArray(false);

        if (200 !== $statusCode) {
            $requestUniqueIdentifier = $content['meta']['uniqId'] ?? '';

            $errorMessage = $content['errors'][0]['message'] ?? '';

            throw new HttpTransportException(sprintf('Unable to send an Email: '.$errorMessage.'. UniqId: (%s).', $requestUniqueIdentifier), $response);
        }

        $messageId = $content['meta']['uniqId'] ?? '';

        $sentMessage->setMessageId($messageId);

        return $response;
    }

    private function convertAddresses(array $input): array
    {
        return array_map(
            fn (Address $address) => [
                'email' => $address->getAddress(),
                'name' => $address->getName(),
                'messageId' => bin2hex(random_bytes(10)) . $address->getAddress()
            ],
            $input
        );
    }

    private function includeTagsAndHeadersInPayload(Email $email, array &$currentPayload): void
    {
        $headers = $email->getHeaders();

        $headersToBypass = ['from', 'sender', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'content-type', 'accept', 'api-key'];

        foreach ($headers->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            if ($header instanceof TagHeader) {
                $currentPayload['tags'][] = $header->getValue();
                continue;
            }

            if ($header instanceof MetadataHeader) {
                $currentPayload['headers']['X-'.ucfirst(strtolower($header->getKey()))] = $header->getValue();
                continue;
            }

            if ($header instanceof ParameterizedHeader) {
                if ('messageids' === $name) {
                    $index = 0;
                    foreach ($currentPayload['to'] as $to) {
                        foreach ($header->getParameters() as $email => $messageId) {
                            if ($to['email'] === $email)
                                $currentPayload['to'][$index]['messageId'] = $messageId;
                        }
                        $index++;
                    }
                }
            }

            if ('templateid' === $name) {
                if (!isset($currentPayload['content']))
                    $currentPayload['content'] = [];

                $currentPayload['content']['templateId'] = (int) $header->getValue();

                continue;
            }

            $currentPayload['headers'][$name] = $header->getValue();
        }
    }

    private function includeAttachmentsInPayload(Email $email, array &$currentPayload): void
    {
        $currentPayload['attachments'] = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $currentPayload['attachments'][] = [
                'fileName' => $filename,
                'fileMime' => $attachment->getMediaType(),
                'fileContent' => base64_encode(str_replace("\r\n", '', $attachment->bodyToString())),
            ];
        }
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'smtpAccount' => $this->fromSmtp,
            'from' => [
                'email' => $envelope->getSender()->getAddress(),
                'name' => $envelope->getSender()->getName(),
            ],
            'to' => $this->convertAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
        ];

        if ($email->getReplyTo()) {
            $payload['replyTo'] = $this->convertAddresses($email->getReplyTo());
        }

        if ($email->getCc()) {
            $payload['cc'] = $this->convertAddresses($email->getCc());
        }

        if ($email->getBcc()) {
            $payload['bcc'] = $this->convertAddresses($email->getBcc());
        }

        if ($email->getTextBody()) {
            $payload['content']['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $payload['content']['html'] = $email->getHtmlBody();
        }

        $this->includeTagsAndHeadersInPayload($email, $payload);
        $this->includeAttachmentsInPayload($email, $payload);

        return $payload;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.redlink.pl').($this->port ? ':'.$this->port : '');
    }
}
