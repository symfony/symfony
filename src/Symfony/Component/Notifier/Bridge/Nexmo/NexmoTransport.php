<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Nexmo;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
final class NexmoTransport extends AbstractTransport
{
    protected const HOST = 'rest.nexmo.com';

    private $apiKey;
    private $apiSecret;
    private $from;

    public function __construct(string $apiKey, string $apiSecret, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('nexmo://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/sms/json', [
            'body' => [
                'from' => $this->from,
                'to' => $message->getPhone(),
                'text' => $message->getSubject(),
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ],
        ]);

        $result = $response->toArray(false);
        foreach ($result['messages'] as $msg) {
            if ($msg['status'] ?? false) {
                throw new TransportException('Unable to send the SMS: '.$msg['error-text'].sprintf(' (code %s).', $msg['status']), $response);
            }
        }
    }
}
