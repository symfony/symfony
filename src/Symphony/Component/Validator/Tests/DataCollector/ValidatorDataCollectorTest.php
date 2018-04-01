<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\ConstraintViolation;
use Symphony\Component\Validator\ConstraintViolationList;
use Symphony\Component\Validator\DataCollector\ValidatorDataCollector;
use Symphony\Component\Validator\Validator\TraceableValidator;
use Symphony\Component\Validator\Validator\ValidatorInterface;

class ValidatorDataCollectorTest extends TestCase
{
    public function testCollectsValidatorCalls()
    {
        $originalValidator = $this->createMock(ValidatorInterface::class);
        $validator = new TraceableValidator($originalValidator);

        $collector = new ValidatorDataCollector($validator);

        $violations = new ConstraintViolationList(array(
            $this->createMock(ConstraintViolation::class),
            $this->createMock(ConstraintViolation::class),
        ));
        $originalValidator->method('validate')->willReturn($violations);

        $validator->validate(new \stdClass());

        $collector->lateCollect();

        $calls = $collector->getCalls();

        $this->assertCount(1, $calls);
        $this->assertSame(2, $collector->getViolationsCount());

        $call = $calls[0];

        $this->assertArrayHasKey('caller', $call);
        $this->assertArrayHasKey('context', $call);
        $this->assertArrayHasKey('violations', $call);
        $this->assertCount(2, $call['violations']);
    }

    public function testReset()
    {
        $originalValidator = $this->createMock(ValidatorInterface::class);
        $validator = new TraceableValidator($originalValidator);

        $collector = new ValidatorDataCollector($validator);

        $violations = new ConstraintViolationList(array(
            $this->createMock(ConstraintViolation::class),
            $this->createMock(ConstraintViolation::class),
        ));
        $originalValidator->method('validate')->willReturn($violations);

        $validator->validate(new \stdClass());

        $collector->lateCollect();
        $collector->reset();

        $this->assertCount(0, $collector->getCalls());
        $this->assertSame(0, $collector->getViolationsCount());
    }

    protected function createMock($classname)
    {
        return $this->getMockBuilder($classname)->disableOriginalConstructor()->getMock();
    }
}
