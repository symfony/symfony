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
 * @author Egor Taranov <dev@taranovegor.com>
 */
class KazInfoTehTransport extends AbstractTransport
{
    protected const HOST = 'kazinfoteh.org';

    private string $username;
    private string $password;
    private string $sender;

    public function __construct(string $username, #[\SensitiveParameter] string $password, string $sender, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->sender = $sender;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('kaz-info-teh://%s?sender=%s', $this->getEndpoint(), $this->sender);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage
            && str_starts_with($message->getPhone(), '77')
            && 11 === \strlen($message->getPhone())
        ;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage || !$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $originator = $message->getFrom() ?: $this->sender;

        $endpoint = sprintf('http://%s/api', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'query' => [
                'action' => 'sendmessage',
                'username' => $this->username,
                'password' => $this->password,
                'recipient' => $message->getPhone(),
                'messagetype' => 'SMS:TEXT',
                'originator' => $originator,
                'messagedata' => $message->getSubject(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote KazInfoTeh server.', $response, 0, $e);
        }

        try {
            $content = new \SimpleXMLElement($response->getContent(false));
        } catch (\Exception $e) {
            throw new TransportException('Unable to send the SMS: "Couldn\'t read response".', $response, previous: $e);
        }

        if (200 !== $statusCode || '0' !== (string) $content->statuscode) {
            $error = (string) $content->statusmessage ?: $content->errormessage ?: 'unknown error';

            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $error), $response);
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
