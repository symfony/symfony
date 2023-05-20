<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Violation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default implementation of {@link ConstraintViolationBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal since version 2.5. Code against ConstraintViolationBuilderInterface instead.
 */
class ConstraintViolationBuilder implements ConstraintViolationBuilderInterface
{
    private ConstraintViolationList $violations;
    private string|\Stringable $message;
    private array $parameters;
    private mixed $root;
    private mixed $invalidValue;
    private string $propertyPath;
    private TranslatorInterface $translator;
    private string|false|null $translationDomain;
    private ?int $plural = null;
    private ?Constraint $constraint;
    private ?string $code = null;
    private mixed $cause = null;

    public function __construct(ConstraintViolationList $violations, ?Constraint $constraint, string|\Stringable $message, array $parameters, mixed $root, ?string $propertyPath, mixed $invalidValue, TranslatorInterface $translator, string|false $translationDomain = null)
    {
        $this->violations = $violations;
        $this->message = $message;
        $this->parameters = $parameters;
        $this->root = $root;
        $this->propertyPath = $propertyPath ?? '';
        $this->invalidValue = $invalidValue;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->constraint = $constraint;
    }

    public function atPath(string $path): static
    {
        $this->propertyPath = PropertyPath::append($this->propertyPath, $path);

        return $this;
    }

    public function setParameter(string $key, string $value): static
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function setTranslationDomain(string $translationDomain): static
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * @return $this
     */
    public function disableTranslation(): static
    {
        $this->translationDomain = false;

        return $this;
    }

    public function setInvalidValue(mixed $invalidValue): static
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    public function setPlural(int $number): static
    {
        $this->plural = $number;

        return $this;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function setCause(mixed $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function addViolation(): void
    {
        $parameters = null === $this->plural ? $this->parameters : (['%count%' => $this->plural] + $this->parameters);
        if (false === $this->translationDomain) {
            $translatedMessage = strtr($this->message, $parameters);
        } else {
            $translatedMessage = $this->translator->trans(
                $this->message,
                $parameters,
                $this->translationDomain
            );
        }

        $this->violations->add(new ConstraintViolation(
            $translatedMessage,
            $this->message,
            $this->parameters,
            $this->root,
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        ));
    }
}
