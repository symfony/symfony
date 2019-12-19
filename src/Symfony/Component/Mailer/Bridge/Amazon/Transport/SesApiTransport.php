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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kevin Verschaeve
 */
class SesApiTransport extends AbstractApiTransport
{
    private const HOST = 'email.%region%.amazonaws.com';

    private $accessKey;
    private $secretKey;
    private $region;

    /**
     * @param string $region Amazon SES region (currently one of us-east-1, us-west-2, or eu-west-1)
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
        return sprintf('ses+api://%s@%s', $this->accessKey, $this->getEndpoint());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $date = gmdate('D, d M Y H:i:s e');
        $auth = sprintf('AWS3-HTTPS AWSAccessKeyId=%s,Algorithm=HmacSHA256,Signature=%s', $this->accessKey, $this->getSignature($date));

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint(), [
            'headers' => [
                'X-Amzn-Authorization' => $auth,
                'Date' => $date,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $payload = $this->getPayload($email, $envelope),
        ]);

        $result = new \SimpleXMLElement($response->getContent(false));
        if (200 !== $response->getStatusCode()) {
            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $result->Error->Message, $result->Error->Code), $response);
        }

        $property = $payload['Action'].'Result';

        $sentMessage->setMessageId($result->{$property}->MessageId);

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

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'Action' => 'SendRawEmail',
            'RawMessage.Data' => base64_encode($email->toString()),
            'Source' => $envelope->getSender()->toString(),
        ];

        $headerRecipients = $this->getRecipientAddressesFromHeaders($email);
        $envelopeRecipients = array_map(static function (Address $recipient) {
            return $recipient->getAddress();
        }, $envelope->getRecipients());
        $requiresDestinations = count(array_diff($headerRecipients, $envelopeRecipients)) > 0;

        if ($requiresDestinations) {
            $payload['Destinations.member'] = $this->stringifyAddresses($envelope->getRecipients());
        }

        dd($email->toString(), $payload);
        return $payload;
    }

    /**
     * @return string[]
     */
    private function getRecipientAddressesFromHeaders(Email $email): array
    {
        $headers = $email->getHeaders();

        $recipients = [];
        foreach (['to', 'cc', 'bcc'] as $name) {
            foreach ($headers->all($name) as $header) {
                /* @var MailboxListHeader $header */
                foreach ($header->getAddresses() as $address) {
                    $recipients[] = $address->getAddress();
                }
            }
        }

        return $recipients;
    }
}
