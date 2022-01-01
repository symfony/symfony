<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OrangeSms;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrangeSmsTransport extends AbstractTransport
{
    protected const HOST = 'https://api.orange.com';

    private $clientID;
    private $clientSecret;
    private $from;
    private $senderName;

    public function __construct(string $clientID, string $clientSecret, string $from, ?string $senderName, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->clientID = $clientID;
        $this->clientSecret = $clientSecret;
        $this->from = $from;
        $this->senderName = $senderName;


        parent::__construct($client, $dispatcher);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }
        
        $url = $this->getEndpoint() . '/smsmessaging/v1/outbound/' . urlencode('tel:' . $this->from) . '/requests';
        $headers = [
            'Authorization' =>  'Bearer ' . $this->getAccessToken(),
            'Content-Type'  =>  'application/json'
        ];

        $args = [
            'outboundSMSMessageRequest' => [
                'address'                   =>  'tel:' . $message->getPhone(),
                'senderAddress'             =>  'tel:' . $this->from,
                'outboundSMSTextMessage'    =>  [
                    'message'   =>  $message->getSubject()
                ]
            ]
        ];

        if (null !== $this->senderName) {
            $args['outboundSMSMessageRequest']['senderName'] = urlencode($this->senderName);
        }

        $response = $this->client->request('POST', $url, [
            'headers'   => $headers,
            'json'      => $args
        ]);

        if (201 != $response->getStatusCode()) {
            $content = $response->toArray(false);
            $errorMessage = $content['requestError']['serviceException']['messageId'] ?? '';
            $errorInfo = $content['requestError']['serviceException']['text'] ?? '';

            throw new TransportException(sprintf('Unable to send the SMS: '.$errorMessage.' (%s).', $errorInfo), $response);
        }

        return new SentMessage($message, (string) $this);
    }

    public function getAccessToken()
    {
        $url = self::HOST . '/oauth/v3/token';
        $credentials = $this->clientID . ':' . $this->clientSecret;
        $headers = [
            'Authorization' =>  'Basic ' . base64_encode($credentials),
            'Content-Type'  =>  'application/x-www-form-urlencoded',
            'Accept'        =>  'application/json'
        ];
        $args = array('grant_type' => 'client_credentials');

        $response = $this->client->request('POST', $url, [
            'headers'   =>  $headers,
            'body'      => $args
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new TransportException('Get Access Token Failled', $response);
        }
        
        return $response->toArray()['access_token'];
    }

    public function __toString(): string
    {
        return sprintf('orangesms://%s?from=%s', $this->getEndpoint(), $this->from);
    }
}
