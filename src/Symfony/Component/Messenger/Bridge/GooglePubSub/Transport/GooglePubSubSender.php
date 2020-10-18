<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Transport;

use AsyncAws\Core\Exception\Http\HttpException;
use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\Topic;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 */
class GooglePubSubSender implements SenderInterface
{
    private $topic;
    private $serializer;

    public function __construct(Topic $topic, SerializerInterface $serializer)
    {
        $this->topic      = $topic;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        try {
            $this->topic->publish(
                new Message(
                    [
                        'data'       => $encodedMessage['body'],
                        'attributes' => $encodedMessage['headers'] ?? []
                    ]
                )
            );
        } catch (GoogleException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }
}
