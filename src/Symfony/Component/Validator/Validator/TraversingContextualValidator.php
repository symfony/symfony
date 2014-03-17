<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnsupportedMetadataException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\CollectionNode;
use Symfony\Component\Validator\Node\GenericNode;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;
use Symfony\Component\Validator\Util\PropertyPath;

/**
 * Default implementation of {@link ContextualValidatorInterface}.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TraversingContextualValidator implements ContextualValidatorInterface
{
    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var NodeTraverserInterface
     */
    private $nodeTraverser;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * Creates a validator for the given context.
     *
     * @param ExecutionContextInterface $context         The execution context
     * @param NodeTraverserInterface    $nodeTraverser   The node traverser
     * @param MetadataFactoryInterface  $metadataFactory The factory for fetching
     *                                                   the metadata of validated
     *                                                   objects
     */
    public function __construct(ExecutionContextInterface $context, NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory)
    {
        $this->context = $context;
        $this->defaultPropertyPath = $context->getPropertyPath();
        $this->defaultGroups = array($context->getGroup() ?: Constraint::DEFAULT_GROUP);
        $this->nodeTraverser = $nodeTraverser;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function atPath($path)
    {
        $this->defaultPropertyPath = $this->context->getPropertyPath($path);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, $constraints = null, $groups = null)
    {
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        if (null !== $constraints) {
            if (!is_array($constraints)) {
                $constraints = array($constraints);
            }

            $metadata = new GenericMetadata();
            $metadata->addConstraints($constraints);

            $node = new GenericNode(
                $value,
                is_object($value) ? spl_object_hash($value) : null,
                $metadata,
                $this->defaultPropertyPath,
                $groups
            );
        } elseif (is_array($value) || $value instanceof \Traversable && !$this->metadataFactory->hasMetadataFor($value)) {
            $node = new CollectionNode(
                $value,
                $this->defaultPropertyPath,
                $groups
            );
        } elseif (is_object($value)) {
            $metadata = $this->metadataFactory->getMetadataFor($value);

            if (!$metadata instanceof ClassMetadataInterface) {
                throw new UnsupportedMetadataException(sprintf(
                    'The metadata factory should return instances of '.
                    '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                    'got: "%s".',
                    is_object($metadata) ? get_class($metadata) : gettype($metadata)
                ));
            }

            $node = new ClassNode(
                $value,
                spl_object_hash($value),
                $metadata,
                $this->defaultPropertyPath,
                $groups
            );
        } else {
            throw new RuntimeException(sprintf(
                'Cannot validate values of type "%s" automatically. Please '.
                'provide a constraint.',
                gettype($value)
            ));
        }

        $this->nodeTraverser->traverse(array($node), $this->context);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validateProperty($object, $propertyName, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            // Cannot be UnsupportedMetadataException because of BC with
            // Symfony < 2.5
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $cacheKey = spl_object_hash($object);
        $nodes = array();

        foreach ($propertyMetadatas as $propertyMetadata) {
            $propertyValue = $propertyMetadata->getPropertyValue($object);

            $nodes[] = new PropertyNode(
                $propertyValue,
                $cacheKey.':'.$propertyName,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups
            );
        }

        $this->nodeTraverser->traverse($nodes, $this->context);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            // Cannot be UnsupportedMetadataException because of BC with
            // Symfony < 2.5
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $cacheKey = spl_object_hash($object);
        $nodes = array();

        foreach ($propertyMetadatas as $propertyMetadata) {
            $nodes[] = new PropertyNode(
                $value,
                $cacheKey.':'.$propertyName,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups,
                $groups
            );
        }

        $this->nodeTraverser->traverse($nodes, $this->context);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getViolations()
    {
        return $this->context->getViolations();
    }

    /**
     * Normalizes the given group or list of groups to an array.
     *
     * @param mixed $groups The groups to normalize
     *
     * @return array A group array
     */
    protected function normalizeGroups($groups)
    {
        if (is_array($groups)) {
            return $groups;
        }

        return array($groups);
    }
}
