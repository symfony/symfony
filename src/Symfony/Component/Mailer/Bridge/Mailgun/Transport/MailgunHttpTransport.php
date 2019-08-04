<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractHttpTransport;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MailgunHttpTransport extends AbstractHttpTransport
{
    private const ENDPOINT = 'https://api.%region_dot%mailgun.net/v3/%domain%/messages.mime';
    private $key;
    private $domain;
    private $region;

    public function __construct(string $key, string $domain, string $region = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->key = $key;
        $this->domain = $domain;
        $this->region = $region;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function getName(): string
    {
        return sprintf('http://%s@mailgun?region=%s', $this->domain, $this->region);
    }

    protected function doSendHttp(SentMessage $message): ResponseInterface
    {
        $body = new FormDataPart([
            'to' => implode(',', $this->stringifyAddresses($message->getEnvelope()->getRecipients())),
            'message' => new DataPart($message->toString(), 'message.mime'),
        ]);
        $headers = [];
        foreach ($body->getPreparedHeaders()->all() as $header) {
            $headers[] = $header->toString();
        }
        $endpoint = str_replace(['%domain%', '%region_dot%'], [urlencode($this->domain), 'us' !== ($this->region ?: 'us') ? $this->region.'.' : ''], self::ENDPOINT);
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        if (200 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $error['message'], $response->getStatusCode()), $response);
        }

        return $response;
    }
}
