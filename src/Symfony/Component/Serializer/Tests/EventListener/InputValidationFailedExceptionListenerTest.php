<?php

namespace Symfony\Component\Serializer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\EventListener\InputValidationFailedExceptionListener;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\DummyDto;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\InputValidationFailedException;

class InputValidationFailedExceptionListenerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ConstraintViolationListNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    /**
     * @dataProvider provideExceptions
     */
    public function testExceptionHandling(\Throwable $e, ?string $expected)
    {
        $listener = new InputValidationFailedExceptionListener($this->serializer, new NullLogger());
        $event = new ExceptionEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $e);

        $listener($event);

        if (null === $expected) {
            $this->assertFalse($event->hasResponse(), 'Unexpected response');
        } else {
            $this->assertTrue($event->hasResponse(), 'Expected a response');
            $this->assertStringContainsString($expected, $event->getResponse()->getContent());
        }
    }

    public function provideExceptions(): \Generator
    {
        yield 'Unrelated exception' => [new \Exception('Nothing to see here'), null];
        yield 'Validation exception' => [new InputValidationFailedException(new DummyDto(), ConstraintViolationList::createFromMessage('This value should not be blank')), 'This value should not be blank'];
    }
}
