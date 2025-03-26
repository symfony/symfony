<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Test;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\CompoundValidator;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A test case to ease testing Compound Constraints.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @template T of Compound
 */
abstract class CompoundConstraintTestCase extends TestCase
{
    protected ValidatorInterface $validator;
    protected ?ConstraintViolationListInterface $violationList = null;
    protected ExecutionContextInterface $context;
    protected string $root;

    private mixed $validatedValue;

    protected function setUp(): void
    {
        $this->root = 'root';
        $this->validator = $this->createValidator();
        $this->context = $this->createContext($this->validator);
    }

    protected function validateValue(mixed $value): void
    {
        $this->validator->inContext($this->context)->validate($this->validatedValue = $value, $this->createCompound());
    }

    protected function createValidator(): ValidatorInterface
    {
        return Validation::createValidator();
    }

    protected function createContext(?ValidatorInterface $validator = null): ExecutionContextInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())->method('trans')->willReturnArgument(0);

        return new ExecutionContext($validator ?? $this->createValidator(), $this->root, $translator);
    }

    public function assertViolationsRaisedByCompound(Constraint|array $constraints): void
    {
        if ($constraints instanceof Constraint) {
            $constraints = [$constraints];
        }

        $validator = new CompoundValidator();
        $context = $this->createContext();
        $validator->initialize($context);

        $validator->validate($this->validatedValue, new class($constraints) extends Compound {
            public function __construct(private array $testedConstraints)
            {
                parent::__construct();
            }

            protected function getConstraints(array $options): array
            {
                return $this->testedConstraints;
            }
        });

        $expectedViolations = iterator_to_array($context->getViolations());

        if (!$expectedViolations) {
            throw new ExpectationFailedException(\sprintf('Expected at least one violation for constraint(s) "%s", got none raised.', implode(', ', array_map(fn ($constraint) => $constraint::class, $constraints))));
        }

        $failedToAssertViolations = [];
        reset($expectedViolations);
        foreach ($this->context->getViolations() as $violation) {
            if ($violation != current($expectedViolations)) {
                $failedToAssertViolations[] = $violation;
            }

            next($expectedViolations);
        }

        $this->assertSame(
            [],
            $failedToAssertViolations,
            \sprintf('Expected violation(s) for constraint(s) %s to be raised by compound.',
                implode(', ', array_map(fn ($violation) => ($violation->getConstraint())::class, $failedToAssertViolations))
            )
        );
    }

    public function assertViolationsCount(int $count): void
    {
        $this->assertCount($count, $this->context->getViolations());
    }

    protected function assertNoViolation(): void
    {
        $violationsCount = \count($this->context->getViolations());
        $this->assertSame(0, $violationsCount, \sprintf('No violation expected. Got %d.', $violationsCount));
    }

    /**
     * @return T
     */
    abstract protected function createCompound(): Compound;
}
