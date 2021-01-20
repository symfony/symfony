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

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransport extends AbstractTransport
{
    private $publisher;
    private $publisherId;
    private $topics;

    /**
     * @param string|string[]|null $topics
     */
    public function __construct(PublisherInterface $publisher, string $publisherId, $topics = null, ?HttpClientInterface $client = null, ?EventDispatcherInterface $dispatcher = null)
    {
        if (null !== $topics && !\is_array($topics) && !\is_string($topics)) {
            throw new \TypeError(sprintf('"%s()" expects parameter 3 to be an array of strings, a string or null, "%s" given.', __METHOD__, get_debug_type($topics)));
        }

        $this->publisher = $publisher;
        $this->publisherId = $publisherId;
        $this->topics = $topics ?? 'https://symfony.com/notifier';

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('mercure://%s?%s', $this->publisherId, http_build_query(['topic' => $this->topics]));
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

        if (null === $options) {
            $options = new MercureOptions($this->topics);
        }

        // @see https://www.w3.org/TR/activitystreams-core/#jsonld
        $update = new Update($options->getTopics() ?? $this->topics, json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'summary' => $message->getSubject(),
        ]), $options->isPrivate(), $options->getId(), $options->getType(), $options->getRetry());

        try {
            $messageId = ($this->publisher)($update);

            $sentMessage = new SentMessage($message, (string) $this);
            $sentMessage->setMessageId($messageId);

            return $sentMessage;
        } catch (HttpExceptionInterface $e) {
            throw new TransportException('Unable to post the Mercure message: '.$e->getResponse()->getContent(false), $e->getResponse(), $e->getCode(), $e);
        } catch (ExceptionInterface $e) {
            throw new RuntimeException('Unable to post the Mercure message: '.$e->getMessage(), $e->getCode(), $e);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException('Unable to post the Mercure message: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
