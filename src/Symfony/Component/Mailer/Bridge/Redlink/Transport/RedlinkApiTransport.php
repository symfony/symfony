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
use Symfony\Component\Mime\Header\Headers;
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

            throw new HttpTransportException(sprintf('Unable to send an Email: ' . $errorMessage . '. UniqId: (%s).', $requestUniqueIdentifier), $response);
        }

        $messageId = $content['data'][0]['externalId'] ?? '';

        $sentMessage->setMessageId($messageId);

        return $response;
    }

    private function covertAddresses(array $input)
    {
        return array_map(
            fn (Address $address) => ['email' => $address->getAddress(), 'name' => $address->getName()],
            $input
        );
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'smtpAccount' => $this->fromSmtp,
            'from' => [
                'email' => $envelope->getSender()->getAddress(),
                'name' => $envelope->getSender()->getName()
            ],
            'to' => $this->covertAddresses($email->getTo()),
            'subject' => $email->getSubject(),
        ];

        if ($email->getReplyTo()) {
            $payload['replyTo'] = $this->covertAddresses($email->getReplyTo());
        }

        if ($email->getCc()) {
            $payload['cc'] = $this->covertAddresses($email->getCc());
        }

        if ($email->getBcc()) {
            $payload['bcc'] = $this->covertAddresses($email->getBcc());
        }

        if ($email->getTextBody()) {
            $payload['content']['text'] = $email->getTextBody();
        }

        if ($email->getHtmlBody()) {
            $payload['content']['html'] = $email->getHtmlBody();
        }

        return $payload;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: 'api.redlink.pl') . ($this->port ? ':' . $this->port : '');
    }
}
