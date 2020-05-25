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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 *
 * @experimental in 5.1
 */
final class FreeMobileTransport extends AbstractTransport
{
    protected const HOST = 'https://smsapi.free-mobile.fr/sendmsg';

    private $login;
    private $password;
    private $phone;

    public function __construct(string $login, string $password, string $phone, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $login;
        $this->password = $password;
        $this->phone = $phone;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('freemobile://%s?phone=%s', $this->getEndpoint(), $this->phone);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && $this->phone === $message->getPhone();
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$this->supports($message)) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given) and configured with your phone number.', __CLASS__, SmsMessage::class, \get_class($message)));
        }

        $response = $this->client->request('POST', $this->getEndpoint(), [
            'json' => [
                'user' => $this->login,
                'pass' => $this->password,
                'msg' => $message->getSubject(),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $errors = [
                400 => 'Missing required parameter or wrongly formatted message.',
                402 => 'Too many messages have been sent too fast.',
                403 => 'Service not enabled or wrong credentials.',
                500 => 'Server error, please try again later.',
            ];

            throw new TransportException(sprintf('Unable to send the SMS: error %d: ', $response->getStatusCode()).($errors[$response->getStatusCode()] ?? ''), $response);
        }
    }
}
