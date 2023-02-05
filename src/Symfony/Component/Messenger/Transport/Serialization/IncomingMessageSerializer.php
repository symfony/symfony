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

final class IncomingMessageSerializer implements SerializerInterface
{
    private Serializer $decorated;

    public function __construct(private readonly IncomingMessageClassResolverInterface|string $messageClass, SymfonySerializerInterface $serializer, string $format, array $context)
    {
        $this->decorated = new Serializer($serializer, $format, $context);
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $encodedEnvelope['headers']['type'] ??= $this->resolveType($encodedEnvelope);

        return $this->decorated->decode($encodedEnvelope);
    }

    public function encode(Envelope $envelope): array
    {
        return $this->decorated->encode($envelope);
    }

    private function resolveType(array $encodedEnvelope): string
    {
        return \is_string($this->messageClass) ? $this->messageClass : ($this->messageClass)($encodedEnvelope);
    }
}
