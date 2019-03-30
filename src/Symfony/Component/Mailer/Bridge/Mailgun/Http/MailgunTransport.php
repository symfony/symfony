<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
class MailgunTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.mailgun.net/v3/%domain%/messages.mime';
    private $key;
    private $domain;
    private $client;

    public function __construct(string $key, string $domain, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->domain = $domain;
        $this->client = $client ?? HttpClient::create();

        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $body = new FormDataPart([
            'to' => implode(',', $this->stringifyAddresses($message->getEnvelope()->getRecipients())),
            'message' => new DataPart($message->toString(), 'message.mime'),
        ]);
        $headers = [];
        foreach ($body->getPreparedHeaders()->getAll() as $header) {
            $headers[] = $header->toString();
        }
        $endpoint = str_replace('%domain%', urlencode($this->domain), self::ENDPOINT);
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send an email: %s (code %s).', $error['message'], $response->getStatusCode()));
        }
    }
}
