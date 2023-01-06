<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone;

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
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 */
final class ContactEveryoneTransport extends AbstractTransport
{
    protected const HOST = 'contact-everyone.orange-business.com';

    private string $token;
    private ?string $diffusionName;
    private ?string $category;

    public function __construct(#[\SensitiveParameter] string $token, ?string $diffusionName, ?string $category, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->diffusionName = $diffusionName;
        $this->category = $category;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $dsn = sprintf('contact-everyone://%s', $this->getEndpoint());

        if ($this->diffusionName) {
            $dsn .= sprintf('?diffusionname=%s', $this->diffusionName);
        }

        if ($this->category) {
            $dsn .= sprintf('%scategory=%s', (null === $this->diffusionName) ? '?' : '&', $this->category);
        }

        return $dsn;
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

        if ('' !== $message->getFrom()) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not support "from" in "%s".', __CLASS__, SmsMessage::class));
        }

        $endpoint = sprintf('https://%s/api/light/diffusions/sms', self::HOST);
        $response = $this->client->request('POST', $endpoint, [
            'query' => [
                'xcharset' => 'true',
                'token' => $this->token,
                'to' => $message->getPhone(),
                'msg' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Contact Everyone server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);
            throw new TransportException(sprintf('Unable to send the Contact Everyone message with following error: "%s". For further details, please check this logId: "%s".', $error['message'], $error['logId']), $response);
        }

        $result = $response->getContent(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result ?? '');

        return $sentMessage;
    }
}
