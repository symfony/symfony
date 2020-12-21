<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Debug\SerializerActionFactory;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\Serializer\SerializerInterface;

final class TraceableSerializerTest extends TestCase
{
    private const JSON = 'json';
    private const SERIALIZED_OBJECT = '{serialized-object}';
    /**
     * @var TraceableSerializer
     */
    private $traceableSerializer;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|SerializerInterface
     */
    private $delegateMock;
    /**
     * @var \stdClass
     */
    private $something;
    /**
     * @var array
     */
    private $emptyContext;

    protected function setUp(): void
    {
        $this->delegateMock = $this->createMock(SerializerInterface::class);
        $this->traceableSerializer = new TraceableSerializer($this->delegateMock, new SerializerActionFactory());
        $this->something = new \stdClass();
        $this->emptyContext = [];
    }

    public function testTracerIsSerializer(): void
    {
        self::assertInstanceOf('Symfony\Component\Serializer\SerializerInterface', $this->traceableSerializer);
        self::assertInstanceOf('Symfony\Contracts\Service\ResetInterface', $this->traceableSerializer);
    }

    public function testSerializeMustCallDelegate(): void
    {
        $this->assertSerializeToBeCalled();

        self::assertSame(
            self::SERIALIZED_OBJECT,
            $this->traceableSerializer->serialize($this->something, self::JSON, $this->emptyContext)
        );
    }

    public function testTracerAddsSerializationToSerializationsStack(): void
    {
        $this->assertSerializeToBeCalled();

        $this->callSerialize();

        self::assertCount(1, $this->traceableSerializer->getSerializations());
    }

    public function testCollectedSerializationContainsResultOfSerialization(): void
    {
        $this->assertSerializeToBeCalled();

        $this->callSerialize();

        $serializations = $this->traceableSerializer->getSerializations();
        self::assertSame(self::SERIALIZED_OBJECT, $serializations[0]->result);
    }

    public function testDeserializeMustCallDelegate(): void
    {
        $this->assertDeserializeToBeCalled();

        self::assertSame(
            $this->something,
            $this->traceableSerializer->deserialize(
                self::SERIALIZED_OBJECT,
                \stdClass::class,
                self::JSON,
                $this->emptyContext
            )
        );
    }

    public function testTracerAddsDeserializationToDeserializationsStack(): void
    {
        $this->assertDeserializeToBeCalled();

        $this->callDeserialize();

        self::assertCount(1, $this->traceableSerializer->getDeserializations());
    }

    public function testCollectedDeserializationContainsResultOfDescerialization(): void
    {
        $this->assertDeserializeToBeCalled();

        $this->callDeserialize();

        $deserializations = $this->traceableSerializer->getDeserializations();
        self::assertSame($this->something, $deserializations[0]->result);
    }

    public function testResetClearsSerializationsAndDeserializationsStacks(): void
    {
        $this->assertSerializeToBeCalled();
        $this->assertDeserializeToBeCalled();

        $this->callSerialize();
        $this->callDeserialize();

        $this->traceableSerializer->reset();

        self::assertCount(0, $this->traceableSerializer->getSerializations());
        self::assertCount(0, $this->traceableSerializer->getDeserializations());
    }

    private function assertSerializeToBeCalled(): void
    {
        $this->delegateMock
            ->expects(self::once())
            ->method('serialize')
            ->with($this->something, self::JSON, $this->emptyContext)
            ->willReturn(self::SERIALIZED_OBJECT);
    }

    private function assertDeserializeToBeCalled(): void
    {
        $this->delegateMock
            ->expects(self::once())
            ->method('deserialize')
            ->with(self::SERIALIZED_OBJECT, \stdClass::class, self::JSON, $this->emptyContext)
            ->willReturn($this->something);
    }

    private function callDeserialize(): void
    {
        $this->traceableSerializer->deserialize(
            self::SERIALIZED_OBJECT,
            \stdClass::class,
            self::JSON,
            $this->emptyContext
        );
    }

    private function callSerialize(): void
    {
        $this->traceableSerializer->serialize(
            $this->something,
            self::JSON,
            $this->emptyContext
        );
    }
}
