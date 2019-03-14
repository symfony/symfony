<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\AbstractStringContains;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

abstract class AbstractStringContainsValidatorTestCase extends ConstraintValidatorTestCase
{
    abstract protected function createConstraint(array $options): AbstractStringContains;

    public function testTextAndCallbackMissingMustThrowDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foo bar', $this->createConstraint([]));
    }

    public function testInvalidTextMustThrowDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foo bar', $this->createConstraint(['text' => (object) []]));
    }

    public function testInvalidCallbackMustThrowDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foo bar', $this->createConstraint(['callback' => 'non-existent-function']));
    }

    public function testInvalidCallbackReturnMustThrowDefinitionException(): void
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foo bar', $this->createConstraint(['callback' => static function () {
            return (object) [];
        }]));
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, $this->createConstraint([
            'text' => 'foo',
        ]));

        $this->assertNoViolation();
    }

    abstract public function provideValidCaseSensitiveComparisons(): iterable;

    /**
     * @dataProvider provideValidCaseSensitiveComparisons
     */
    public function testValidCaseSensitiveComparisons($value, $text): void
    {
        $constraint = $this->createConstraint([
            'text' => $text,
            'caseSensitive' => true,
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    abstract public function provideValidCaseInsensitiveComparisons(): iterable;

    /**
     * @dataProvider provideValidCaseInsensitiveComparisons
     */
    public function testValidCaseInsensitiveComparisons($value, $text): void
    {
        $constraint = $this->createConstraint([
            'text' => $text,
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    abstract public function provideInvalidCaseSensitiveComparisons(): iterable;

    /**
     * @dataProvider provideInvalidCaseSensitiveComparisons
     */
    public function testInvalidCaseSensitiveComparisons($value, $text, $errorCode): void
    {
        $constraint = $this->createConstraint([
            'text' => $text,
            'message' => 'myMessage',
            'caseSensitive' => true,
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setCode($errorCode)
            ->assertRaised();
    }

    abstract public function provideInvalidCaseInsensitiveComparisons(): iterable;

    /**
     * @dataProvider provideInvalidCaseInsensitiveComparisons
     */
    public function testInvalidCaseInsensitiveComparisons($value, $text, $errorCode): void
    {
        $constraint = $this->createConstraint([
            'text' => $text,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setCode($errorCode)
            ->assertRaised();
    }

    public function testCallbackMustReceiveContextAndPayload(): void
    {
        $constraint = $this->createConstraint([
            'payload' => 'bar',
            'callback' => function ($context, $payload) {
                $this->assertInstanceOf(ExecutionContextInterface::class, $context);
                $this->assertSame('bar', $payload);

                return [];
            },
        ]);

        $this->validator->validate('foo', $constraint);
    }

    abstract public function provideValidComparisonUsingCallbackWithoutContext(): iterable;

    /**
     * Generate callbacks without context that always return "foo".
     */
    protected function alwaysFooCallbacksWithoutContext(): iterable
    {
        yield __NAMESPACE__.'\alwaysFooFunction';
        yield [FooCallbackClass::class, 'alwaysFooStaticMethod'];
        yield static function () {
            return 'foo';
        };
    }

    /**
     * @dataProvider provideValidComparisonUsingCallbackWithoutContext
     */
    public function testValidComparisonUsingCallbackWithoutContext($value, $callback): void
    {
        $constraint = $this->createConstraint([
            'callback' => $callback,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    abstract public function provideValidComparisonUsingCallbackWithContext(): iterable;

    /**
     * Generate callbacks with context that always return "foo".
     */
    protected function alwaysFooCallbacksWithContext(): iterable
    {
        yield [new FooCallbackClass(), 'alwaysFooMethod'];
    }

    /**
     * @dataProvider provideValidComparisonUsingCallbackWithContext
     */
    public function tesValidComparisonUsingCallbackWithContext($value, $callback): void
    {
        $this->setObject($callback[0]);
        $constraint = $this->createConstraint([
            'callback' => $callback,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }
}

function alwaysFooFunction(): string
{
    return 'foo';
}

class FooCallbackClass
{
    public static function alwaysFooStaticMethod(): string
    {
        return 'foo';
    }

    public function alwaysFooMethod(): string
    {
        return 'foo';
    }
}
