<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractHttpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class SesHttpTransport extends AbstractHttpTransport
{
    private const HOST = 'email.%region%.amazonaws.com';

    private $accessKey;
    private $secretKey;
    private $region;

    /**
     * @param string|null $region Amazon SES region
     */
    public function __construct(string $accessKey, string $secretKey, string $region = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region ?: 'eu-west-1';

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('ses+https://%s@%s', $this->accessKey, $this->getEndpoint());
    }

    protected function doSendHttp(SentMessage $message): ResponseInterface
    {
        $date = gmdate('D, d M Y H:i:s e');
        $auth = sprintf('AWS3-HTTPS AWSAccessKeyId=%s,Algorithm=HmacSHA256,Signature=%s', $this->accessKey, $this->getSignature($date));

        $request = [
            'headers' => [
                'X-Amzn-Authorization' => $auth,
                'Date' => $date,
            ],
            'body' => [
                'Action' => 'SendRawEmail',
                'RawMessage.Data' => base64_encode($message->toString()),
            ],
        ];
        $index = 1;
        foreach ($message->getEnvelope()->getRecipients() as $recipient) {
            $request['body']['Destinations.member.'.$index++] = $recipient->getAddress();
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint(), $request);

        try {
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Amazon server.', $response, 0, $e);
        }

        $result = new \SimpleXMLElement($content);
        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result->Error->Message.sprintf(' (code %d).', $result->Error->Code), $response);
        }

        $message->setMessageId($result->SendRawEmailResult->MessageId);

        return $response;
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: str_replace('%region%', $this->region, self::HOST)).($this->port ? ':'.$this->port : '');
    }

    private function getSignature(string $string): string
    {
        return base64_encode(hash_hmac('sha256', $string, $this->secretKey, true));
    }
}
