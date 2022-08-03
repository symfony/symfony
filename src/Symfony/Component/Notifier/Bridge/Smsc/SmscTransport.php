<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsc;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface as HttpDecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface as HttpTransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Valentin Nazarov <i.kozlice@protonmail.com>
 */
final class SmscTransport extends AbstractTransport
{
    protected const HOST = 'smsc.ru';

    private ?string $login;
    private ?string $password;
    private string $from;

    public function __construct(?string $username, #[\SensitiveParameter] ?string $password, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->login = $username;
        $this->password = $password;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('smsc://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        $body = [
            'login' => $this->login,
            'psw' => $this->password,
            'sender' => $from,
            'phones' => $message->getPhone(),
            'mes' => $message->getSubject(),
            'fmt' => 3, // response as JSON
            'charset' => 'utf-8',
            'time' => '0-24',
        ];

        $endpoint = sprintf('https://%s/sys/send.php', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, ['body' => $body]);

        try {
            $result = $response->toArray();
        } catch (HttpTransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote smsc.ru server.', $response, 0, $e);
        } catch (HttpDecodingExceptionInterface $e) {
            throw new TransportException('Could not decode the response from remote smsc.ru server.', $response, 0, $e);
        } catch (HttpExceptionInterface $e) {
            throw new TransportException('Unexpected response from remote smsc.ru server.', $response, 0, $e);
        }

        if (\array_key_exists('error', $result)) {
            throw new TransportException(sprintf('Unable to send the SMS: code = %d, message = "%s".', $result['error_code'], $result['error']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((string) ($result['id'] ?? ''));

        return $sentMessage;
    }
}
