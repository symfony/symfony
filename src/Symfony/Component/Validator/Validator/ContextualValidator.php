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
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\GenericNode;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;
use Symfony\Component\Validator\Util\PropertyPath;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ContextualValidator implements ContextualValidatorInterface
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

    public function __construct(ExecutionContextInterface $context, NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory)
    {
        $this->context = $context;
        $this->defaultPropertyPath = $context->getPropertyPath();
        $this->defaultGroups = array($context->getGroup() ?: Constraint::DEFAULT_GROUP);
        $this->nodeTraverser = $nodeTraverser;
        $this->metadataFactory = $metadataFactory;
    }

    public function atPath($subPath)
    {
        $this->defaultPropertyPath = $this->context->getPropertyPath($subPath);

        return $this;
    }

    public function validate($value, $constraints, $groups = null)
    {
        if (!is_array($constraints)) {
            $constraints = array($constraints);
        }

        $metadata = new GenericMetadata();
        $metadata->addConstraints($constraints);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        $node = new GenericNode(
            $value,
            $metadata,
            $this->defaultPropertyPath,
            $groups
        );

        $this->nodeTraverser->traverse(array($node), $this->context);

        return $this;
    }

    public function validateObject($object, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        $node = new ClassNode(
            $object,
            $classMetadata,
            $this->defaultPropertyPath,
            $groups
        );

        $this->nodeTraverser->traverse(array($node), $this->context);

        return $this;
    }

    public function validateObjects($objects, $groups = null, $deep = false)
    {
        $constraint = new Traverse(array(
            'traverse' => true,
            'deep' => $deep,
        ));

        return $this->validate($objects, $constraint, $groups);
    }

    public function validateProperty($object, $propertyName, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $nodes = array();

        foreach ($propertyMetadatas as $propertyMetadata) {
            $propertyValue = $propertyMetadata->getPropertyValue($object);

            $nodes[] = new PropertyNode(
                $propertyValue,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups
            );
        }

        $this->nodeTraverser->traverse($nodes, $this->context);

        return $this;
    }

    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        $classMetadata = $this->metadataFactory->getMetadataFor($object);

        if (!$classMetadata instanceof ClassMetadataInterface) {
            throw new ValidatorException(sprintf(
                'The metadata factory should return instances of '.
                '"\Symfony\Component\Validator\Mapping\ClassMetadataInterface", '.
                'got: "%s".',
                is_object($classMetadata) ? get_class($classMetadata) : gettype($classMetadata)
            ));
        }

        $propertyMetadatas = $classMetadata->getPropertyMetadata($propertyName);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;
        $nodes = array();

        foreach ($propertyMetadatas as $propertyMetadata) {
            $nodes[] = new PropertyNode(
                $value,
                $propertyMetadata,
                PropertyPath::append($this->defaultPropertyPath, $propertyName),
                $groups,
                $groups
            );
        }

        $this->nodeTraverser->traverse($nodes, $this->context);

        return $this;
    }

    protected function normalizeGroups($groups)
    {
        if (is_array($groups)) {
            return $groups;
        }

        return array($groups);
    }

    public function getViolations()
    {
        return $this->context->getViolations();
    }
}
