<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\MessageFetcher;

use Symfony\Component\Worker\MessageCollection;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class BufferedMessageFetcher implements MessageFetcherInterface
{
    private $messageFetcher;
    private $options;
    private $messageCollections;
    private $lastBufferingAt;

    public function __construct(MessageFetcherInterface $messageFetcher, array $options = array())
    {
        $this->messageFetcher = $messageFetcher;
        $this->options = array_replace(array(
            'max_buffering_time' => 10,
            'max_messages' => 10,
        ), $options);
        $this->messageCollections = array();
    }

    /**
     * @return MessageCollection|bool A collection of messages, false otherwise
     */
    public function fetchMessages()
    {
        $bufferSize = count($this->messageCollections);

        while ($messageCollection = $this->fetchNextMessage($bufferSize)) {
            $this->messageCollections[] = $messageCollection;
            $bufferSize += count($messageCollection);
            $this->lastBufferingAt = time();
        }

        $isBufferFull = $bufferSize === $this->options['max_messages'];
        $isBufferExpirated = time() - $this->lastBufferingAt >= $this->options['max_buffering_time'] && 0 !== $bufferSize;

        if ($isBufferFull || $isBufferExpirated) {
            $messageCollections = $this->messageCollections;

            $this->messageCollections = array();

            $messageCollection = new MessageCollection();

            foreach ($messageCollections as $msgCollection) {
                foreach ($msgCollection as $message) {
                    $messageCollection->add($message);
                }
            }

            return $messageCollection;
        }

        return false;
    }

    private function fetchNextMessage($bufferSize)
    {
        if ($bufferSize >= $this->options['max_messages']) {
            return false;
        }

        return $this->messageFetcher->fetchMessages();
    }
}
