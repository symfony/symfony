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

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractHttpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MandrillHttpTransport extends AbstractHttpTransport
{
    private const HOST = 'mandrillapp.com';
    private $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mandrill+https://%s', $this->getEndpoint());
    }

    protected function doSendHttp(SentMessage $message): ResponseInterface
    {
        $envelope = $message->getEnvelope();
        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/1.0/messages/send-raw.json', [
            'json' => [
                'key' => $this->key,
                'to' => $this->stringifyAddresses($envelope->getRecipients()),
                'from_email' => $envelope->getSender()->toString(),
                'raw_message' => $message->toString(),
            ],
        ]);

        $result = $response->toArray(false);
        if (200 !== $response->getStatusCode()) {
            if ('error' === ($result['status'] ?? false)) {
                throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $result['message'], $result['code']), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email (code %s).', $result['code']), $response);
        }

        $message->setMessageId($result['_id']);

        return $response;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
