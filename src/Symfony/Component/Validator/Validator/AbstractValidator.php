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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\AdHocMetadata;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\PropertyNode;
use Symfony\Component\Validator\Node\ValueNode;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;
use Symfony\Component\Validator\Util\PropertyPath;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractValidator implements ValidatorInterface
{
    /**
     * @var NodeTraverserInterface
     */
    protected $nodeTraverser;

    /**
     * @var MetadataFactoryInterface
     */
    protected $metadataFactory;

    /**
     * @var string
     */
    protected $defaultPropertyPath = '';

    protected $defaultGroups = array(Constraint::DEFAULT_GROUP);

    public function __construct(NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory)
    {
        $this->nodeTraverser = $nodeTraverser;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * @param ExecutionContextInterface $context
     *
     * @return ContextualValidatorInterface
     */
    public function inContext(ExecutionContextInterface $context)
    {
        return new ContextualValidator($this->nodeTraverser, $this->metadataFactory, $context);
    }

    public function getMetadataFor($object)
    {
        return $this->metadataFactory->getMetadataFor($object);
    }

    public function hasMetadataFor($object)
    {
        return $this->metadataFactory->hasMetadataFor($object);
    }

    protected function traverseObject($object, $groups = null)
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

        $this->nodeTraverser->traverse(array(new ClassNode(
            $object,
            $classMetadata,
            $this->defaultPropertyPath,
            $groups,
            $groups
        )));
    }

    protected function traverseProperty($object, $propertyName, $groups = null)
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
                $groups,
                $groups
            );
        }

        $this->nodeTraverser->traverse($nodes);
    }

    protected function traversePropertyValue($object, $propertyName, $value, $groups = null)
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

        $this->nodeTraverser->traverse($nodes);
    }

    protected function traverseValue($value, $constraints, $groups = null)
    {
        if (!is_array($constraints)) {
            $constraints = array($constraints);
        }

        $metadata = new AdHocMetadata($constraints);
        $groups = $groups ? $this->normalizeGroups($groups) : $this->defaultGroups;

        $this->nodeTraverser->traverse(array(new ValueNode(
            $value,
            $metadata,
            $this->defaultPropertyPath,
            $groups,
            $groups
        )));
    }

    protected function normalizeGroups($groups)
    {
        if (is_array($groups)) {
            return $groups;
        }

        return array($groups);
    }
}
