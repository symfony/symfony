<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\InvariantViolation;

class InvariantViolationTest extends TestCase
{
    public function testItRepresentsAnInvariantViolation(): void
    {
        $exception = new Exception();

        $violation = new InvariantViolation('foo', '"foo" is not an integer.', $exception);

        self::assertSame('foo', $violation->getNormalizedValue());
        self::assertSame('"foo" is not an integer.', $violation->getMessage());
        self::assertSame($exception, $violation->getThrowable());
    }
}
