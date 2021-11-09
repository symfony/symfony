<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\KazInfoTeh;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;

/**
 * @author Egor Taranov <dev@taranovegor.com>
 */
class KazInfoTehTransport extends AbstractTransport
{
    protected const HOST = 'kazinfoteh.org';

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $sender;

    public function __construct(string $username, string $password, string $sender, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->sender = $sender;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf(
            'kazinfoteh://%s?sender=%s',
            $this->getEndpoint(),
            $this->sender
        );
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage
            && 0 === strpos($message->getPhone(), '77')
            && 11 === strlen($message->getPhone())
        ;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $endpoint = sprintf('https://%s/api', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'query' => [
                'action' => 'sendmessage',
                'username' => $this->username,
                'password' => $this->password,
                'recipient' => $message->getPhone(),
                'messagetype' => 'SMS:TEXT',
                'originator' => $this->sender,
                'messagedata' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote TurboSMS server.', $response, 0, $e);
        }

        $content = $response->getContent(false);
        if (200 !== $statusCode || false === strpos($content, '<statuscode>0</statuscode>')) {
            // <statusmessage>There's any text</statusmessage> => [0 => There's any text]
            preg_match('#<statusmessage>(.*)<\/statusmessage>#m', $content, $matches);

            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $matches[1] ?? 'unknown error'), $response);
        }

        return new SentMessage($message, (string) $this);
    }

    protected function getEndpoint(): string
    {
        $endpoint = $this->host ?: $this->getDefaultHost();
        if ($this->getDefaultHost() === $endpoint && null === $this->port) {
            $endpoint .= ':9507';
        } elseif (null !== $this->port) {
            $endpoint .= ':'.$this->port;
        }

        return $endpoint;
    }
}
