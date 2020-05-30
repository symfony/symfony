<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio;

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
final class TwilioTransport extends AbstractTransport
{
    protected const HOST = 'api.twilio.com';

    private $accountSid;
    private $authToken;
    private $from;

    public function __construct(string $accountSid, string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('twilio://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/2010-04-01/Accounts/%s/Messages.json', $this->getEndpoint(), $this->accountSid);
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => $this->accountSid.':'.$this->authToken,
            'body' => [
                'From' => $this->from,
                'To' => $message->getPhone(),
                'Body' => $message->getSubject(),
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.$error['message'].sprintf(' (see %s).', $error['more_info']), $response);
        }
    }
}
