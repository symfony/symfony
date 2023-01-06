<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure;

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransport extends AbstractTransport
{
    private HubInterface $hub;
    private string $hubId;
    private string|array $topics;

    /**
     * @param string|string[]|null $topics
     */
    public function __construct(HubInterface $hub, string $hubId, string|array $topics = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->hub = $hub;
        $this->hubId = $hubId;
        $this->topics = $topics ?? 'https://symfony.com/notifier';

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('mercure://%s?%s', $this->hubId, http_build_query(['topic' => $this->topics], '', '&'));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof MercureOptions);
    }

    /**
     * @see https://symfony.com/doc/current/mercure.html#publishing
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (($options = $message->getOptions()) && !$options instanceof MercureOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, MercureOptions::class));
        }

        $options ??= new MercureOptions($this->topics);

        // @see https://www.w3.org/TR/activitystreams-core/#jsonld
        $update = new Update($options->getTopics() ?? $this->topics, json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'summary' => $message->getSubject(),
        ]), $options->isPrivate(), $options->getId(), $options->getType(), $options->getRetry());

        try {
            $messageId = $this->hub->publish($update);

            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($messageId);

            return $sentMessage;
        } catch (MercureRuntimeException|InvalidArgumentException $e) {
            throw new RuntimeException('Unable to post the Mercure message: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
