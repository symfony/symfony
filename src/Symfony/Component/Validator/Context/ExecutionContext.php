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

use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
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
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * The root value of the validated object graph.
     *
     * @var mixed
     */
    private $root;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $translationDomain;

    /**
     * The violations generated in the current context.
     *
     * @var ConstraintViolationList
     */
    private $violations;

    /**
     * The currently validated value.
     *
     * @var mixed
     */
    private $value;

    /**
     * The currently validated object.
     *
     * @var object|null
     */
    private $object;

    /**
     * The property path leading to the current value.
     *
     * @var string
     */
    private $propertyPath = '';

    /**
     * The current validation metadata.
     *
     * @var MetadataInterface|null
     */
    private $metadata;

    /**
     * The currently validated group.
     *
     * @var string|null
     */
    private $group;

    /**
     * The currently validated constraint.
     *
     * @var Constraint|null
     */
    private $constraint;

    /**
     * Stores which objects have been validated in which group.
     *
     * @var array
     */
    private $validatedObjects = [];

    /**
     * Stores which class constraint has been validated for which object.
     *
     * @var array
     */
    private $validatedConstraints = [];

    /**
     * Stores which objects have been initialized.
     *
     * @var array
     */
    private $initializedObjects;
    private $cachedObjectsRefs;

    /**
     * Creates a new execution context.
     *
     * @param mixed               $root              The root value of the
     *                                               validated object graph
     * @param TranslatorInterface $translator        The translator
     * @param string|null         $translationDomain The translation domain to
     *                                               use for translating
     *                                               violation messages
     *
     * @internal Called by {@link ExecutionContextFactory}. Should not be used
     *           in user code.
     */
    public function __construct(ValidatorInterface $validator, $root, $translator, string $translationDomain = null)
    {
        if (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 3 passed to "%s()" must be an instance of "%s", "%s" given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
        $this->validator = $validator;
        $this->root = $root;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->violations = new ConstraintViolationList();
        $this->cachedObjectsRefs = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function setNode($value, $object, MetadataInterface $metadata = null, $propertyPath)
    {
        $this->value = $value;
        $this->object = $object;
        $this->metadata = $metadata;
        $this->propertyPath = (string) $propertyPath;
    }

    /**
     * {@inheritdoc}
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * {@inheritdoc}
     */
    public function setConstraint(Constraint $constraint)
    {
        $this->constraint = $constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation($message, array $parameters = [])
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

    /**
     * {@inheritdoc}
     */
    public function buildViolation($message, array $parameters = []): ConstraintViolationBuilderInterface
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

    /**
     * {@inheritdoc}
     */
    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        if ($this->value instanceof LazyProperty) {
            return $this->value->getPropertyValue();
        }

        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): ?MetadataInterface
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function getConstraint(): ?Constraint
    {
        return $this->constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function getClassName(): ?string
    {
        return $this->metadata instanceof MemberMetadata || $this->metadata instanceof ClassMetadataInterface ? $this->metadata->getClassName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyName(): ?string
    {
        return $this->metadata instanceof PropertyMetadataInterface ? $this->metadata->getPropertyName() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath($subPath = ''): string
    {
        return PropertyPath::append($this->propertyPath, $subPath);
    }

    /**
     * {@inheritdoc}
     */
    public function markGroupAsValidated($cacheKey, $groupHash)
    {
        if (!isset($this->validatedObjects[$cacheKey])) {
            $this->validatedObjects[$cacheKey] = [];
        }

        $this->validatedObjects[$cacheKey][$groupHash] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isGroupValidated($cacheKey, $groupHash): bool
    {
        return isset($this->validatedObjects[$cacheKey][$groupHash]);
    }

    /**
     * {@inheritdoc}
     */
    public function markConstraintAsValidated($cacheKey, $constraintHash)
    {
        $this->validatedConstraints[$cacheKey.':'.$constraintHash] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isConstraintValidated($cacheKey, $constraintHash): bool
    {
        return isset($this->validatedConstraints[$cacheKey.':'.$constraintHash]);
    }

    /**
     * {@inheritdoc}
     */
    public function markObjectAsInitialized($cacheKey)
    {
        $this->initializedObjects[$cacheKey] = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isObjectInitialized($cacheKey): bool
    {
        return isset($this->initializedObjects[$cacheKey]);
    }

    /**
     * @internal
     *
     * @param object $object
     *
     * @return string
     */
    public function generateCacheKey($object)
    {
        if (!isset($this->cachedObjectsRefs[$object])) {
            $this->cachedObjectsRefs[$object] = spl_object_hash($object);
        }

        return $this->cachedObjectsRefs[$object];
    }
}
