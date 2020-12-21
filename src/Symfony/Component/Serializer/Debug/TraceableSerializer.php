<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class TraceableSerializer implements SerializerInterface, ResetInterface
{
    /**
     * @var Serialization[]
     */
    private $serializations = [];
    /**
     * @var Deserialization[]
     */
    private $deserializations = [];

    private $serializerDelegate;
    private $serializerActionFactory;

    public function __construct(SerializerInterface $serializerDelegate, SerializerActionFactoryInterface $serializerActionFactory)
    {
        $this->serializerDelegate = $serializerDelegate;
        $this->serializerActionFactory = $serializerActionFactory;
    }

    public function serialize($data, string $format, array $context = []): string
    {
        $result = $this->serializerDelegate->serialize($data, $format, $context);
        $this->serializations[] = $this->serializerActionFactory->createSerialization($data, $result, $format, $context);

        return $result;
    }

    public function deserialize($data, string $type, string $format, array $context = [])
    {
        $result = $this->serializerDelegate->deserialize($data, $type, $format, $context);
        $this->deserializations[] = $this->serializerActionFactory->createDeserialization($data, $result, $type, $format, $context);

        return $result;
    }

    /**
     * @return array|Serialization[]
     */
    public function getSerializations(): array
    {
        return $this->serializations;
    }

    /**
     * @return array|Deserialization[]
     */
    public function getDeserializations(): array
    {
        return $this->deserializations;
    }

    public function reset(): void
    {
        $this->serializations = [];
        $this->deserializations = [];
    }
}
