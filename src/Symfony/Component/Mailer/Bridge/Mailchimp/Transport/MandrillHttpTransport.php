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

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractHttpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class MandrillHttpTransport extends AbstractHttpTransport
{
    use MandrillHeadersTrait;

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
                'to' => array_map(function (Address $recipient): string {
                    return $recipient->getAddress();
                }, $envelope->getRecipients()),
                'from_email' => $envelope->getSender()->getAddress(),
                'from_name' => $envelope->getSender()->getName(),
                'raw_message' => $message->toString(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mandrill server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            if ('error' === ($result['status'] ?? false)) {
                throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $result['code']), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email (code %d).', $result['code']), $response);
        }

        $message->setMessageId($result[0]['_id']);

        return $response;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
