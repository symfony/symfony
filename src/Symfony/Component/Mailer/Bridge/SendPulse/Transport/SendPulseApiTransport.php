<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\SendPulse\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see https://sendpulse.com/integrations/api/smtp
 */
final class SendPulseApiTransport extends AbstractApiTransport
{
    private ?string $accessToken = null;
    private float $expiresAt = 0;
    private ?string $tokenEndpoint = null;

    public function __construct(
        #[\SensitiveParameter] private string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'sendpulse+api://'.$this->getEndpoint();
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $payload = ['email' => $this->getPayload($email, $envelope)];
        $token = $this->getAccessToken();
        $response = $this->request('smtp/emails', ['auth_bearer' => $token, 'json' => $payload]);

        // Only retry an explicit authentication rejection, never an ambiguous send failure.
        if (401 === $response->getStatusCode()) {
            $response->cancel();
            $this->accessToken = null;
            $response = $this->request('smtp/emails', ['auth_bearer' => $this->getAccessToken(), 'json' => $payload]);
        }

        $result = $this->decodeResponse($response);
        if (true !== ($result['result'] ?? null) || !\is_string($result['id'] ?? null) || '' === $result['id']) {
            throw new HttpTransportException('SendPulse did not confirm that the email was accepted.', $response);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    private function getAccessToken(): string
    {
        $endpoint = $this->getEndpoint();
        if (null !== $this->accessToken && $this->tokenEndpoint === $endpoint && microtime(true) < $this->expiresAt) {
            return $this->accessToken;
        }

        $startedAt = microtime(true);
        $response = $this->request('oauth/access_token', ['body' => [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]]);
        $result = $this->decodeResponse($response);
        if (!\is_string($result['access_token'] ?? null) || '' === $result['access_token'] || !is_numeric($result['expires_in'] ?? null) || 0 >= $result['expires_in']) {
            throw new HttpTransportException('SendPulse returned an invalid OAuth token response.', $response);
        }

        $this->tokenEndpoint = $endpoint;
        $this->expiresAt = $startedAt + max(0, (int) $result['expires_in'] - 60);

        return $this->accessToken = $result['access_token'];
    }

    private function request(string $path, array $options): ResponseInterface
    {
        try {
            $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/'.$path, $options + ['max_redirects' => 0]);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the SendPulse server.', 0, $e);
        }

        return $response;
    }

    private function decodeResponse(ResponseInterface $response): array
    {
        try {
            $status = $response->getStatusCode();
            if (200 !== $status) {
                throw new HttpTransportException(\sprintf('SendPulse returned HTTP %d.', $status), $response);
            }

            return $response->toArray(false);
        } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new HttpTransportException('Unable to read the SendPulse response.', $response, 0, $e);
        }
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'subject' => $email->getSubject(),
            'from' => $this->address($envelope->getSender()),
            'to' => [],
        ];
        $cc = array_map(static fn (Address $address) => $address->getAddress(), $email->getCc());
        $bcc = array_map(static fn (Address $address) => $address->getAddress(), $email->getBcc());
        foreach ($envelope->getRecipients() as $recipient) {
            $field = \in_array($recipient->getAddress(), $bcc, true) ? 'bcc' : (\in_array($recipient->getAddress(), $cc, true) ? 'cc' : 'to');
            $payload[$field][] = $this->address($recipient);
        }

        if (!$payload['to']) {
            throw new TransportException('SendPulse API requires at least one To recipient. Use the SMTP transport for messages with only Cc or Bcc recipients.');
        }

        if (null !== $html = $this->bodyToString($email->getHtmlBody())) {
            $payload['html'] = base64_encode($html);
        }
        if (null !== $text = $this->bodyToString($email->getTextBody())) {
            $payload['text'] = $text;
        }
        if (\count($email->getReplyTo()) > 1) {
            throw new TransportException('SendPulse API supports only one Reply-To address.');
        }
        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->address($replyTo[0]);
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            if ('inline' === $headers->getHeaderBody('Content-Disposition')) {
                throw new TransportException('SendPulse API does not support inline attachments. Use the SMTP transport instead.');
            }
            $name = $headers->getHeaderParameter('Content-Disposition', 'filename');
            if (null === $name || '' === $name || isset($attachments[$name])) {
                throw new TransportException('SendPulse API requires unique, non-empty attachment filenames.');
            }
            $attachments[$name] = base64_encode($attachment->getBody());
        }
        if ($attachments) {
            $payload['attachments_binary'] = (object) $attachments;
        }

        return $payload;
    }

    /**
     * @param resource|string|null $body
     */
    private function bodyToString(mixed $body): ?string
    {
        if (!\is_resource($body)) {
            return $body;
        }

        if (stream_get_meta_data($body)['seekable']) {
            rewind($body);
        }

        if (false === $contents = stream_get_contents($body)) {
            throw new TransportException('Unable to read the SendPulse message body.');
        }

        return $contents;
    }

    private function address(Address $address): array
    {
        return ['email' => $address->getAddress(), 'name' => $address->getName()];
    }

    private function getEndpoint(): string
    {
        return ($this->host ?: 'api.sendpulse.com').($this->port ? ':'.$this->port : '');
    }
}
