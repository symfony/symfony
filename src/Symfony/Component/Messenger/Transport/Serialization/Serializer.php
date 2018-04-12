<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class Serializer implements DecoderInterface, EncoderInterface
{
    private $serializer;
    private $format;

    public function __construct(SerializerInterface $serializer, string $format = 'json')
    {
        $this->serializer = $serializer;
        $this->format = $format;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedMessage)
    {
        if (empty($encodedMessage['body']) || empty($encodedMessage['headers'])) {
            throw new \InvalidArgumentException('Encoded message should have at least a `body` and some `headers`.');
        }

        if (empty($encodedMessage['headers']['type'])) {
            throw new \InvalidArgumentException('Encoded message does not have a `type` header.');
        }

        return $this->serializer->deserialize($encodedMessage['body'], $encodedMessage['headers']['type'], $this->format);
    }

    /**
     * {@inheritdoc}
     */
    public function encode($message): array
    {
        return array(
            'body' => $this->serializer->serialize($message, $this->format),
            'headers' => array('type' => \get_class($message)),
        );
    }
}
