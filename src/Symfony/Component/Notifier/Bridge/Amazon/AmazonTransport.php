<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Amazon;

use AsyncAws\Sns\SnsClient;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 *
 * @experimental in 5.3
 */
final class AmazonTransport extends AbstractTransport
{
    /** @var SnsClient */
    private $snsClient;

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
        return $message instanceof SmsMessage || $message instanceof ChatMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage && !$message instanceof ChatMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" and "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, ChatMessage::class, get_debug_type($message)));
        }

        if ($message instanceof ChatMessage) {
            if (!$message->getOptions() instanceof AmazonSnsOptions) {
                throw new LogicException(sprintf('The "%s" transport only supports "%s" as "%s" for "%s".', __CLASS__, AmazonSnsOptions::class, MessageOptionsInterface::class, ChatMessage::class));
            }
            $options = $message->getOptions()->toArray();
        }
        $options['Message'] = $message->getSubject();
        $options[($message instanceof ChatMessage) ? 'TopicArn' : 'PhoneNumber'] = $message->getRecipientId();

        $response = $this->snsClient->publish($options);

        $message = new SentMessage($message, (string) $this);
        $message->setMessageId($response->getMessageId());

        return $message;
    }
}
