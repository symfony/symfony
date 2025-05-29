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
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Default implementation of {@link ConstraintViolationBuilderInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class ConstraintViolationBuilder implements ConstraintViolationBuilderInterface
{
    private string $propertyPath;
    private ?string $message = null;
    private ?int $plural = null;
    private ?string $code = null;
    private mixed $cause = null;

    public function __construct(
        private ?ConstraintViolationListInterface $violations,
        private ?Constraint $constraint,
        private string|\Stringable $messageTemplate,
        private array $parameters,
        private mixed $root,
        ?string $propertyPath,
        private mixed $invalidValue,
        private TranslatorInterface|null $translator = null,
        private string|false|null $translationDomain = null,
    ) {
        $this->propertyPath = $propertyPath ?? '';
    }

    public static function fromViolation(ConstraintViolationInterface $violation): static
    {
        $builder = new self(
            null,
            $violation->getConstraint(),
            $violation->getMessageTemplate(),
            $violation->getParameters(),
            $violation->getRoot(),
            $violation->getPropertyPath(),
            $violation->getInvalidValue(),
        );

        $builder->message = $violation->getMessage();
        $builder->plural = $violation->getPlural();
        $builder->code = $violation->getCode();
        $builder->cause = $violation->getCause();

        return $builder;
    }

    public function setPath(string $path): static
    {
        $this->propertyPath = $path;

        return $this;
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
        $this->translator = null;

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
        if (null === $this->violations) {
            throw new \LogicException('Violation can be added only within execution context.');
        }

        $this->violations->add($this->getViolation());
    }

    public function getViolation(): ConstraintViolationInterface
    {
        return new ConstraintViolation(
            $this->message ??= $this->translateMessage(),
            $this->messageTemplate,
            $this->parameters,
            $this->root,
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }

    private function translateMessage(): string
    {
        $parameters = null === $this->plural ? $this->parameters : (['%count%' => $this->plural] + $this->parameters);

        if (null === $this->translator || false === $this->translationDomain) {
            return strtr($this->messageTemplate, $parameters);
        }

        return $this->translator->trans(
            $this->messageTemplate,
            $parameters,
            $this->translationDomain
        );
    }
}
