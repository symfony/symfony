<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FreeMobile;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
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
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FreeMobileTransport extends AbstractTransport
{
    protected const HOST = 'smsapi.free-mobile.fr/sendmsg';

    private string $login;
    private string $password;
    private string $phone;

    public function __construct(string $login, #[\SensitiveParameter] string $password, string $phone, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->password = $password;
        $this->phone = str_replace('+33', '0', $phone);

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('freemobile://%s?phone=%s', $this->getEndpoint(), $this->phone);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && $this->phone === str_replace('+33', '0', $message->getPhone());
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        /** @var SmsMessage $message */
        if ('' !== $message->getFrom()) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not support "from" in "%s".', __CLASS__, SmsMessage::class));
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'query' => [
                'user' => $this->login,
                'pass' => $this->password,
                'msg' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote FreeMobile server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $errors = [
                400 => 'Missing required parameter or wrongly formatted message.',
                402 => 'Too many messages have been sent too fast.',
                403 => 'Service not enabled or wrong credentials.',
                500 => 'Server error, please try again later.',
            ];

            throw new TransportException(sprintf('Unable to send the SMS: error %d: ', $statusCode).($errors[$statusCode] ?? ''), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
