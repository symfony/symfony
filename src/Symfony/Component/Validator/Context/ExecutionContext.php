<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Context;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\MemberMetadata;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Util\PropertyPath;
use Symfony\Component\Validator\Validator\LazyProperty;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The context used and created by {@link ExecutionContextFactory}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextInterface
 *
 * @internal since version 2.5. Code against ExecutionContextInterface instead.
 */
class ExecutionContext implements ExecutionContextInterface
{
    private ValidatorInterface $validator;

    /**
     * The root value of the validated object graph.
     */
    private mixed $root;

    private TranslatorInterface $translator;
    private ?string $translationDomain;

    /**
     * The violations generated in the current context.
     */
    private ConstraintViolationList $violations;

    /**
     * The currently validated value.
     */
    private mixed $value = null;

    /**
     * The currently validated object.
     */
    private ?object $object = null;

    /**
     * The property path leading to the current value.
     */
    private string $propertyPath = '';

    /**
     * The current validation metadata.
     */
    private ?MetadataInterface $metadata = null;

    /**
     * The currently validated group.
     */
    private ?string $group = null;

    /**
     * The currently validated constraint.
     */
    private ?Constraint $constraint = null;

    /**
     * Stores which objects have been validated in which group.
     *
     * @var bool[][]
     */
    private array $validatedObjects = [];

    /**
     * Stores which class constraint has been validated for which object.
     *
     * @var bool[]
     */
    private array $validatedConstraints = [];

    /**
     * Stores which objects have been initialized.
     *
     * @var bool[]
     */
    private array $initializedObjects = [];

    /**
     * @var \SplObjectStorage<object, string>
     */
    private \SplObjectStorage $cachedObjectsRefs;

    /**
     * @internal Called by {@link ExecutionContextFactory}. Should not be used in user code.
     */
    public function __construct(ValidatorInterface $validator, mixed $root, TranslatorInterface $translator, ?string $translationDomain = null)
    {
        $this->validator = $validator;
        $this->root = $root;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->violations = new ConstraintViolationList();
        $this->cachedObjectsRefs = new \SplObjectStorage();
    }

    public function setNode(mixed $value, ?object $object, ?MetadataInterface $metadata, string $propertyPath): void
    {
        $this->value = $value;
        $this->object = $object;
        $this->metadata = $metadata;
        $this->propertyPath = $propertyPath;
    }

    public function setGroup(?string $group): void
    {
        $this->group = $group;
    }

    public function setConstraint(Constraint $constraint): void
    {
        $this->constraint = $constraint;
    }

    public function addViolation(string|\Stringable $message, array $parameters = []): void
    {
        $this->violations->add(new ConstraintViolation(
            $this->translator->trans($message, $parameters, $this->translationDomain),
            $message,
            $parameters,
            $this->root,
            $this->propertyPath,
            $this->getValue(),
            null,
            null,
            $this->constraint
        ));
    }

    public function buildViolation(string|\Stringable $message, array $parameters = []): ConstraintViolationBuilderInterface
    {
        return new ConstraintViolationBuilder(
            $this->violations,
            $this->constraint,
            $message,
            $parameters,
            $this->root,
            $this->propertyPath,
            $this->getValue(),
            $this->translator,
            $this->translationDomain
        );
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    public function getRoot(): mixed
    {
        return $this->root;
    }

    public function getValue(): mixed
    {
        if ($this->value instanceof LazyProperty) {
            return $this->value->getPropertyValue();
        }

        return $this->value;
    }

    public function getObject(): ?object
    {
        return $this->object;
    }

    public function getMetadata(): ?MetadataInterface
    {
        return $this->metadata;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getConstraint(): ?Constraint
    {
        return $this->constraint;
    }

    public function getClassName(): ?string
    {
        return $this->metadata instanceof MemberMetadata || $this->metadata instanceof ClassMetadataInterface ? $this->metadata->getClassName() : null;
    }

    public function getPropertyName(): ?string
    {
        return $this->metadata instanceof PropertyMetadataInterface ? $this->metadata->getPropertyName() : null;
    }

    public function getPropertyPath(string $subPath = ''): string
    {
        return PropertyPath::append($this->propertyPath, $subPath);
    }

    public function markGroupAsValidated(string $cacheKey, string $groupHash): void
    {
        if (!isset($this->validatedObjects[$cacheKey])) {
            $this->validatedObjects[$cacheKey] = [];
        }

        $this->validatedObjects[$cacheKey][$groupHash] = true;
    }

    public function isGroupValidated(string $cacheKey, string $groupHash): bool
    {
        return isset($this->validatedObjects[$cacheKey][$groupHash]);
    }

    public function markConstraintAsValidated(string $cacheKey, string $constraintHash): void
    {
        $this->validatedConstraints[$cacheKey.':'.$constraintHash] = true;
    }

    public function isConstraintValidated(string $cacheKey, string $constraintHash): bool
    {
        return isset($this->validatedConstraints[$cacheKey.':'.$constraintHash]);
    }

    public function markObjectAsInitialized(string $cacheKey): void
    {
        $this->initializedObjects[$cacheKey] = true;
    }

    public function isObjectInitialized(string $cacheKey): bool
    {
        return isset($this->initializedObjects[$cacheKey]);
    }

    /**
     * @internal
     */
    public function generateCacheKey(object $object): string
    {
        if (!isset($this->cachedObjectsRefs[$object])) {
            $this->cachedObjectsRefs[$object] = spl_object_hash($object);
        }

        return $this->cachedObjectsRefs[$object];
    }

    public function __clone()
    {
        $this->violations = clone $this->violations;
    }
}
