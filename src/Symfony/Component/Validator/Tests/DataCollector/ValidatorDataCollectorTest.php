<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\DataCollector\ValidatorDataCollector;
use Symfony\Component\Validator\Validator\TraceableValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidatorDataCollectorTest extends TestCase
{
    public function testCollectsValidatorCalls()
    {
        $originalValidator = self::createMock(ValidatorInterface::class);
        $validator = new TraceableValidator($originalValidator);

        $collector = new ValidatorDataCollector($validator);

        $violations = new ConstraintViolationList([
            self::createMock(ConstraintViolation::class),
            self::createMock(ConstraintViolation::class),
        ]);
        $originalValidator->method('validate')->willReturn($violations);

        $validator->validate(new \stdClass());

        $collector->lateCollect();

        $calls = $collector->getCalls();

        self::assertCount(1, $calls);
        self::assertSame(2, $collector->getViolationsCount());

        $call = $calls[0];

        self::assertArrayHasKey('caller', $call);
        self::assertArrayHasKey('context', $call);
        self::assertArrayHasKey('violations', $call);
        self::assertCount(2, $call['violations']);
    }

    public function testReset()
    {
        $originalValidator = self::createMock(ValidatorInterface::class);
        $validator = new TraceableValidator($originalValidator);

        $collector = new ValidatorDataCollector($validator);

        $violations = new ConstraintViolationList([
            self::createMock(ConstraintViolation::class),
            self::createMock(ConstraintViolation::class),
        ]);
        $originalValidator->method('validate')->willReturn($violations);

        $validator->validate(new \stdClass());

        $collector->lateCollect();
        $collector->reset();

        self::assertCount(0, $collector->getCalls());
        self::assertSame(0, $collector->getViolationsCount());
    }
}
