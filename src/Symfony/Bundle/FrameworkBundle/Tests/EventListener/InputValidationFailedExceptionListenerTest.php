<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\EventListener\InputValidationFailedExceptionListener;
use Symfony\Bundle\FrameworkBundle\Exception\UnparsableInputException;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation\DummyDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

class InputValidationFailedExceptionListenerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
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
        yield 'Unparsable exception' => [new UnparsableInputException('Input is a mess.'), '{"message":"Invalid input"}'];

        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        $input = new DummyDto();
        $input->itMustBeTrue = false;

        yield 'Validation exception' => [new ValidationFailedException($input, $validator->validate($input)), 'This value should not be blank'];
    }
}
