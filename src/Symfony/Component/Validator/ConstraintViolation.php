<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Default implementation of {@ConstraintViolationInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintViolation implements ConstraintViolationInterface
{
    private string|\Stringable $message;
    private ?string $messageTemplate;
    private array $parameters;
    private ?int $plural;
    private mixed $root;
    private ?string $propertyPath;
    private mixed $invalidValue;
    private ?Constraint $constraint;
    private ?string $code;
    private mixed $cause;

    /**
     * Creates a new constraint violation.
     *
     * @param string|\Stringable $message         The violation message as a string or a stringable object
     * @param string|null        $messageTemplate The raw violation message
     * @param array              $parameters      The parameters to substitute in the
     *                                            raw violation message
     * @param mixed              $root            The value originally passed to the
     *                                            validator
     * @param string|null        $propertyPath    The property path from the root
     *                                            value to the invalid value
     * @param mixed              $invalidValue    The invalid value that caused this
     *                                            violation
     * @param int|null           $plural          The number for determining the plural
     *                                            form when translating the message
     * @param string|null        $code            The error code of the violation
     * @param Constraint|null    $constraint      The constraint whose validation
     *                                            caused the violation
     * @param mixed              $cause           The cause of the violation
     */
    public function __construct(string|\Stringable $message, ?string $messageTemplate, array $parameters, mixed $root, ?string $propertyPath, mixed $invalidValue, ?int $plural = null, ?string $code = null, ?Constraint $constraint = null, mixed $cause = null)
    {
        $this->message = $message;
        $this->messageTemplate = $messageTemplate;
        $this->parameters = $parameters;
        $this->plural = $plural;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
        $this->constraint = $constraint;
        $this->code = $code;
        $this->cause = $cause;
    }

    public function __toString(): string
    {
        if (\is_object($this->root)) {
            $class = 'Object('.$this->root::class.')';
        } elseif (\is_array($this->root)) {
            $class = 'Array';
        } else {
            $class = (string) $this->root;
        }

        $propertyPath = (string) $this->propertyPath;

        if ('' !== $propertyPath && '[' !== $propertyPath[0] && '' !== $class) {
            $class .= '.';
        }

        if (null !== ($code = $this->code) && '' !== $code) {
            $code = ' (code '.$code.')';
        }

        return $class.$propertyPath.":\n    ".$this->getMessage().$code;
    }

    public function getMessageTemplate(): string
    {
        return (string) $this->messageTemplate;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getPlural(): ?int
    {
        return $this->plural;
    }

    public function getMessage(): string|\Stringable
    {
        return $this->message;
    }

    public function getRoot(): mixed
    {
        return $this->root;
    }

    public function getPropertyPath(): string
    {
        return (string) $this->propertyPath;
    }

    public function getInvalidValue(): mixed
    {
        return $this->invalidValue;
    }

    /**
     * Returns the constraint whose validation caused the violation.
     */
    public function getConstraint(): ?Constraint
    {
        return $this->constraint;
    }

    /**
     * Returns the cause of the violation.
     */
    public function getCause(): mixed
    {
        return $this->cause;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
}
