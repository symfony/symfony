<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Http\AbstractHttpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Kevin Verschaeve
 *
 * @experimental in 4.3
 */
class MandrillTransport extends AbstractHttpTransport
{
    private const ENDPOINT = 'https://mandrillapp.com/api/1.0/messages/send-raw.json';
    private $key;

    public function __construct(string $key, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;

        parent::__construct($client, $dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $envelope = $message->getEnvelope();
        $response = $this->client->request('POST', self::ENDPOINT, [
            'json' => [
                'key' => $this->key,
                'to' => $this->stringifyAddresses($envelope->getRecipients()),
                'from_email' => $envelope->getSender()->toString(),
                'raw_message' => $message->toString(),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $result = $response->toArray(false);
            if ('error' === ($result['status'] ?? false)) {
                throw new TransportException(sprintf('Unable to send an email: %s (code %s).', $result['message'], $result['code']));
            }

            throw new TransportException(sprintf('Unable to send an email (code %s).', $result['code']));
        }
    }
}
