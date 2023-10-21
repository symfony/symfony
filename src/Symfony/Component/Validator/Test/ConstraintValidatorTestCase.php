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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\Constraint\IsInstanceOf;
use PHPUnit\Framework\Constraint\IsNull;
use PHPUnit\Framework\Constraint\LogicalOr;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A test case to ease testing Constraint Validators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @template T of ConstraintValidatorInterface
 */
abstract class ConstraintValidatorTestCase extends TestCase
{
    /**
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * @var T
     */
    protected $validator;

    protected $group;
    protected $metadata;
    protected $object;
    protected $value;
    protected $root;
    protected $propertyPath;
    protected $constraint;
    protected $defaultTimezone;
    private string $defaultLocale;
    private array $expectedViolations;
    private int $call;

    protected function setUp(): void
    {
        $this->group = 'MyGroup';
        $this->metadata = null;
        $this->object = null;
        $this->value = 'InvalidValue';
        $this->root = 'root';
        $this->propertyPath = 'property.path';

        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();

        $this->context = $this->createContext();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');

        $this->expectedViolations = [];
        $this->call = 0;

        $this->setDefaultTimezone('UTC');
    }

    protected function tearDown(): void
    {
        $this->restoreDefaultTimezone();

        \Locale::setDefault($this->defaultLocale);
    }

    protected function setDefaultTimezone(?string $defaultTimezone)
    {
        // Make sure this method cannot be called twice before calling
        // also restoreDefaultTimezone()
        if (null === $this->defaultTimezone) {
            $this->defaultTimezone = date_default_timezone_get();
            date_default_timezone_set($defaultTimezone);
        }
    }

    protected function restoreDefaultTimezone()
    {
        if (null !== $this->defaultTimezone) {
            date_default_timezone_set($this->defaultTimezone);
            $this->defaultTimezone = null;
        }
    }

    protected function createContext()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())->method('trans')->willReturnArgument(0);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->any())
            ->method('validate')
            ->willReturnCallback(fn () => $this->expectedViolations[$this->call++] ?? new ConstraintViolationList());

        $context = new ExecutionContext($validator, $this->root, $translator);
        $context->setGroup($this->group);
        $context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
        $context->setConstraint($this->constraint);

        $contextualValidatorMockBuilder = $this->getMockBuilder(AssertingContextualValidator::class)
            ->setConstructorArgs([$context]);
        $contextualValidatorMethods = [
            'atPath',
            'validate',
            'validateProperty',
            'validatePropertyValue',
            'getViolations',
        ];

        $contextualValidatorMockBuilder->onlyMethods($contextualValidatorMethods);
        $contextualValidator = $contextualValidatorMockBuilder->getMock();
        $contextualValidator->expects($this->any())
            ->method('atPath')
            ->willReturnCallback(fn ($path) => $contextualValidator->doAtPath($path));
        $contextualValidator->expects($this->any())
            ->method('validate')
            ->willReturnCallback(fn ($value, $constraints = null, $groups = null) => $contextualValidator->doValidate($value, $constraints, $groups));
        $contextualValidator->expects($this->any())
            ->method('validateProperty')
            ->willReturnCallback(fn ($object, $propertyName, $groups = null) => $contextualValidator->validateProperty($object, $propertyName, $groups));
        $contextualValidator->expects($this->any())
            ->method('validatePropertyValue')
            ->willReturnCallback(fn ($objectOrClass, $propertyName, $value, $groups = null) => $contextualValidator->doValidatePropertyValue($objectOrClass, $propertyName, $value, $groups));
        $contextualValidator->expects($this->any())
            ->method('getViolations')
            ->willReturnCallback(fn () => $contextualValidator->doGetViolations());
        $validator->expects($this->any())
            ->method('inContext')
            ->with($context)
            ->willReturn($contextualValidator);

        return $context;
    }

    protected function setGroup(?string $group)
    {
        $this->group = $group;
        $this->context->setGroup($group);
    }

    protected function setObject(mixed $object)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new ClassMetadata($object::class)
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setProperty(mixed $object, string $property)
    {
        $this->object = $object;
        $this->metadata = \is_object($object)
            ? new PropertyMetadata($object::class, $property)
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setValue(mixed $value)
    {
        $this->value = $value;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setRoot(mixed $root)
    {
        $this->root = $root;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setPropertyPath(string $propertyPath)
    {
        $this->propertyPath = $propertyPath;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function expectNoValidate()
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectNoValidate();
    }

    protected function expectValidateAt(int $i, string $propertyPath, mixed $value, string|GroupSequence|array|null $group)
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expectValidation($i, $propertyPath, $value, $group, function ($passedConstraints) {
            $expectedConstraints = new LogicalOr();
            $expectedConstraints->setConstraints([new IsNull(), new IsIdentical([]), new IsInstanceOf(Valid::class)]);

            Assert::assertThat($passedConstraints, $expectedConstraints);
        });
    }

    protected function expectValidateValue(int $i, mixed $value, array $constraints = [], string|GroupSequence|array $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, null, $value, $group, function ($passedConstraints) use ($constraints) {
            if (\is_array($constraints) && !\is_array($passedConstraints)) {
                $passedConstraints = [$passedConstraints];
            }

            Assert::assertEquals($constraints, $passedConstraints);
        });
    }

    protected function expectFailingValueValidation(int $i, mixed $value, array $constraints, string|GroupSequence|array|null $group, ConstraintViolationInterface $violation)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, null, $value, $group, function ($passedConstraints) use ($constraints) {
            if (\is_array($constraints) && !\is_array($passedConstraints)) {
                $passedConstraints = [$passedConstraints];
            }

            Assert::assertEquals($constraints, $passedConstraints);
        }, $violation);
    }

    protected function expectValidateValueAt(int $i, string $propertyPath, mixed $value, Constraint|array $constraints, string|GroupSequence|array $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expectValidation($i, $propertyPath, $value, $group, function ($passedConstraints) use ($constraints) {
            Assert::assertEquals($constraints, $passedConstraints);
        });
    }

    protected function expectViolationsAt(int $i, mixed $value, Constraint $constraint)
    {
        $context = $this->createContext();

        $validatorClassname = $constraint->validatedBy();

        $validator = new $validatorClassname();
        $validator->initialize($context);
        $validator->validate($value, $constraint);

        $this->expectedViolations[] = $context->getViolations();

        return $context->getViolations();
    }

    protected function assertNoViolation()
    {
        $this->assertSame(0, $violationsCount = \count($this->context->getViolations()), sprintf('0 violation expected. Got %u.', $violationsCount));
    }

    protected function buildViolation(string|\Stringable $message): ConstraintViolationAssertion
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    /**
     * @return ConstraintValidatorInterface
     *
     * @psalm-return T
     */
    abstract protected function createValidator();
}

final class ConstraintViolationAssertion
{
    private ExecutionContextInterface $context;

    /**
     * @var ConstraintViolationAssertion[]
     */
    private array $assertions;

    private string $message;
    private array $parameters = [];
    private mixed $invalidValue = 'InvalidValue';
    private string $propertyPath = 'property.path';
    private ?int $plural = null;
    private ?string $code = null;
    private ?Constraint $constraint;
    private mixed $cause = null;

    /**
     * @internal
     */
    public function __construct(ExecutionContextInterface $context, string $message, Constraint $constraint = null, array $assertions = [])
    {
        $this->context = $context;
        $this->message = $message;
        $this->constraint = $constraint;
        $this->assertions = $assertions;
    }

    /**
     * @return $this
     */
    public function atPath(string $path): static
    {
        $this->propertyPath = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function setParameter(string $key, string $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTranslationDomain(?string $translationDomain): static
    {
        // no-op for BC

        return $this;
    }

    /**
     * @return $this
     */
    public function setInvalidValue(mixed $invalidValue): static
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    /**
     * @return $this
     */
    public function setPlural(int $number): static
    {
        $this->plural = $number;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return $this
     */
    public function setCause(mixed $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function buildNextViolation(string $message): self
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $this->constraint, $assertions);
    }

    public function assertRaised(): void
    {
        $expected = [];
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }
        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        Assert::assertSame($expectedCount = \count($expected), $violationsCount = \count($violations), sprintf('%u violation(s) expected. Got %u.', $expectedCount, $violationsCount));

        reset($violations);

        foreach ($expected as $violation) {
            Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation(): ConstraintViolation
    {
        return new ConstraintViolation(
            $this->message,
            $this->message,
            $this->parameters,
            $this->context->getRoot(),
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }
}

/**
 * @internal
 */
class AssertingContextualValidator implements ContextualValidatorInterface
{
    private ExecutionContextInterface $context;
    private bool $expectNoValidate = false;
    private int $atPathCalls = -1;
    private array $expectedAtPath = [];
    private int $validateCalls = -1;
    private array $expectedValidate = [];

    public function __construct(ExecutionContextInterface $context)
    {
        $this->context = $context;
    }

    public function __destruct()
    {
        if ($this->expectedAtPath) {
            throw new ExpectationFailedException('Some expected validation calls for paths were not done.');
        }

        if ($this->expectedValidate) {
            throw new ExpectationFailedException('Some expected validation calls for values were not done.');
        }
    }

    public function atPath(string $path): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doAtPath(string $path): static
    {
        Assert::assertFalse($this->expectNoValidate, 'No validation calls have been expected.');

        if (!isset($this->expectedAtPath[++$this->atPathCalls])) {
            throw new ExpectationFailedException(sprintf('Validation for property path "%s" was not expected.', $path));
        }

        $expectedPath = $this->expectedAtPath[$this->atPathCalls];
        unset($this->expectedAtPath[$this->atPathCalls]);

        Assert::assertSame($expectedPath, $path);

        return $this;
    }

    public function validate(mixed $value, Constraint|array $constraints = null, string|GroupSequence|array $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidate(mixed $value, Constraint|array $constraints = null, string|GroupSequence|array $groups = null): static
    {
        Assert::assertFalse($this->expectNoValidate, 'No validation calls have been expected.');

        if (!isset($this->expectedValidate[++$this->validateCalls])) {
            return $this;
        }

        [$expectedValue, $expectedGroup, $expectedConstraints, $violation] = $this->expectedValidate[$this->validateCalls];
        unset($this->expectedValidate[$this->validateCalls]);

        Assert::assertSame($expectedValue, $value);
        $expectedConstraints($constraints);
        Assert::assertSame($expectedGroup, $groups);

        if (null !== $violation) {
            $this->context->addViolation($violation->getMessage(), $violation->getParameters());
        }

        return $this;
    }

    public function validateProperty(object $object, string $propertyName, string|GroupSequence|array $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidateProperty(object $object, string $propertyName, string|GroupSequence|array $groups = null): static
    {
        return $this;
    }

    public function validatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, string|GroupSequence|array $groups = null): static
    {
        throw new \BadMethodCallException();
    }

    /**
     * @return $this
     */
    public function doValidatePropertyValue(object|string $objectOrClass, string $propertyName, mixed $value, string|GroupSequence|array $groups = null): static
    {
        return $this;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        throw new \BadMethodCallException();
    }

    public function doGetViolations(): ConstraintViolationListInterface
    {
        return $this->context->getViolations();
    }

    public function expectNoValidate(): void
    {
        $this->expectNoValidate = true;
    }

    public function expectValidation(string $call, ?string $propertyPath, mixed $value, string|GroupSequence|array|null $group, callable $constraints, ConstraintViolationInterface $violation = null): void
    {
        if (null !== $propertyPath) {
            $this->expectedAtPath[$call] = $propertyPath;
        }

        $this->expectedValidate[$call] = [$value, $group, $constraints, $violation];
    }
}
