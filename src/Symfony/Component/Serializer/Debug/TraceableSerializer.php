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

    /**
     * @var SerializerInterface
     */
    private $delegate;

    public function __construct(SerializerInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    public function serialize($data, string $format, array $context = []): string
    {
        $serialization = new Serialization($data, $format, $context);
        $serialization->result = $this->delegate->serialize($data, $format, $context);
        $this->serializations[] = $serialization;

        return $serialization->result;
    }

    public function deserialize($data, string $type, string $format, array $context = [])
    {
        $deserialization = new Deserialization($data, $type, $format, $context);
        $deserialization->result = $this->delegate->deserialize($data, $type, $format, $context);
        $this->deserializations[] = $deserialization;

        return $deserialization->result;
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
