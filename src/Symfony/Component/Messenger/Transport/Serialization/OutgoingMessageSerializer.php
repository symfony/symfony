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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final class OutgoingMessageSerializer implements SerializerInterface
{
    private Serializer $decorated;

    public function __construct(SymfonySerializerInterface $serializer, string $format, array $context)
    {
        $this->decorated = new Serializer($serializer, $format, $context);
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \LogicException(sprintf('Cannot use "%s" to decode message.', __CLASS__));
    }

    public function encode(Envelope $envelope): array
    {
        $encode = $this->decorated->encode(Envelope::wrap($envelope->getMessage()));

        unset($encode['headers']['type']);

        return $encode;
    }
}
