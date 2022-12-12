<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns;

use AsyncAws\Sns\SnsClient;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 */
final class AmazonSnsTransport extends AbstractTransport
{
    private SnsClient $snsClient;

    public function __construct(SnsClient $snsClient, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->snsClient = $snsClient;
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $configuration = $this->snsClient->getConfiguration();

        return sprintf('sns://%s?region=%s', $this->getEndpoint(), $configuration->get('region'));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage || ($message instanceof ChatMessage && $message->getOptions() instanceof AmazonSnsOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, sprintf('"%s" or "%s"', SmsMessage::class, ChatMessage::class), $message);
        }

        if ($message instanceof SmsMessage && '' !== $message->getFrom()) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not support "from" in "%s".', __CLASS__, SmsMessage::class));
        }

        if ($message instanceof ChatMessage && $message->getOptions() instanceof AmazonSnsOptions) {
            $options = $message->getOptions()->toArray();
        }
        $options['Message'] = $message->getSubject();
        $options[($message instanceof ChatMessage) ? 'TopicArn' : 'PhoneNumber'] = $message->getRecipientId();

        try {
            $response = $this->snsClient->publish($options);
            $message = new SentMessage($message, (string) $this);
            $message->setMessageId($response->getMessageId());
        } catch (\Exception $exception) {
            $info = isset($response) ? $response->info() : [];
            throw new TransportException('Unable to send the message.', $info['response'] ?? null, $info['status'] ?? 0, $exception);
        }

        return $message;
    }
}
