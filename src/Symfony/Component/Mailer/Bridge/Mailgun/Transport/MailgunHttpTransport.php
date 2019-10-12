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
    private const HOST = 'api.%region_dot%mailgun.net';

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

    public function __toString(): string
    {
        return sprintf('mailgun+https://%s?domain=%s', $this->getEndpoint(), $this->domain);
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

        $endpoint = sprintf('%s/v3/%s/messages.mime', $this->getEndpoint(), urlencode($this->domain));
        $response = $this->client->request('POST', 'https://'.$endpoint, [
            'auth_basic' => 'api:'.$this->key,
            'headers' => $headers,
            'body' => $body->bodyToIterable(),
        ]);

        $result = $response->toArray(false);
        if (200 !== $response->getStatusCode()) {
            if ('application/json' === $response->getHeaders(false)['content-type'][0]) {
                throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $result['message'], $response->getStatusCode()), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $response->getContent(false), $response->getStatusCode()), $response);
        }

        $message->setMessageId($result['id']);

        return $response;
    }

    private function getEndpoint(): ?string
    {
        $host = $this->host ?: str_replace('%region_dot%', 'us' !== ($this->region ?: 'us') ? $this->region.'.' : '', self::HOST);

        return $host.($this->port ? ':'.$this->port : '');
    }
}
