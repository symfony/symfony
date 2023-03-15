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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TwilioTransport extends AbstractTransport
{
    protected const HOST = 'api.twilio.com';

    private string $accountSid;
    private string $authToken;
    private string $from;

    public function __construct(string $accountSid, #[\SensitiveParameter] string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
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
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof TwilioOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof TwilioOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, TwilioOptions::class));
        }

        $from = $message->getFrom() ?: $this->from;

        if (!preg_match('/^[a-zA-Z0-9\s]{2,11}$/', $from) && !preg_match('/^\+[1-9]\d{1,14}$/', $from)) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $from));
        }

        $endpoint = sprintf('https://%s/2010-04-01/Accounts/%s/Messages.json', $this->getEndpoint(), $this->accountSid);
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        $body = [
            'From' => $from,
            'To' => $message->getPhone(),
            'Body' => $message->getSubject(),
        ];
        if (isset($options['webhook_url'])) {
            $body['StatusCallback'] = $options['webhook_url'];
        }
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => $this->accountSid.':'.$this->authToken,
            'body' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Twilio server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.$error['message'].sprintf(' (see %s).', $error['more_info']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['sid']);

        return $sentMessage;
    }
}
