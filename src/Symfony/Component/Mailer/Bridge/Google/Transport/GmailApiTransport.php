<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\Google\TokenManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Gmail API transport using OAuth2 service account authentication.
 *
 * @author Pascal CESCON <pascal.cescon@gmail.com>
 */
final class GmailApiTransport extends AbstractApiTransport
{
    private const API_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/%s/messages/send';

    public function __construct(
        private readonly TokenManager $tokenManager,
        private readonly string $userEmail,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('gmail+api://%s', $this->userEmail);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $endpoint = \sprintf(self::API_ENDPOINT, $this->userEmail);

        $headers = $email->getPreparedHeaders();
        $this->applyEnvelope($headers, $envelope);

        // getPreparedHeaders() works on a clone and generates a Message-ID when the email
        // has none, so it would differ from the one SentMessage already settled on
        if (!$email->getHeaders()->has('Message-ID')) {
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', $sentMessage->getMessageId());
        }

        $encodedMessage = $this->base64UrlEncode($headers->toString().$email->getBody()->toString());

        $response = $this->client->request('POST', $endpoint, [
            'json' => [
                'raw' => $encodedMessage,
            ],
            'auth_bearer' => $this->tokenManager->getToken(),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the Gmail API server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send email via Gmail API: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        $result = $response->toArray();

        // Set the message ID from Gmail's response
        if (isset($result['id'])) {
            $sentMessage->setMessageId($result['id']);
        }

        return $response;
    }

    /**
     * The Gmail API takes the recipients from the raw message, so the envelope has to be
     * expressed in its headers: addresses the envelope drops are removed from To and Cc,
     * and the ones it adds travel in Bcc, which Gmail honors and strips from the delivered
     * message. The envelope sender cannot be expressed, Gmail sends as the impersonated user.
     */
    private function applyEnvelope(Headers $headers, Envelope $envelope): void
    {
        $recipients = array_map(static fn (Address $address): string => strtolower($address->getAddress()), $envelope->getRecipients());
        $kept = [];

        foreach (['To', 'Cc'] as $name) {
            if (!($header = $headers->get($name)) instanceof MailboxListHeader) {
                continue;
            }

            $addresses = array_values(array_filter($header->getAddresses(), static fn (Address $address): bool => \in_array(strtolower($address->getAddress()), $recipients, true)));
            $headers->remove($name);

            if ($addresses) {
                $headers->addMailboxListHeader($name, $addresses);
                foreach ($addresses as $address) {
                    $kept[] = strtolower($address->getAddress());
                }
            }
        }

        if ($bcc = array_values(array_filter($envelope->getRecipients(), static fn (Address $address): bool => !\in_array(strtolower($address->getAddress()), $kept, true)))) {
            $headers->addMailboxListHeader('Bcc', $bcc);
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
