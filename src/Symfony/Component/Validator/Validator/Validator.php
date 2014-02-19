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

use Symfony\Component\Validator\Context\ExecutionContextFactoryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Validator implements ValidatorInterface
{
    /**
     * @var ExecutionContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @var NodeTraverserInterface
     */
    protected $nodeTraverser;

    /**
     * @var MetadataFactoryInterface
     */
    protected $metadataFactory;

    public function __construct(ExecutionContextFactoryInterface $contextFactory, NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory)
    {
        $this->contextFactory = $contextFactory;
        $this->nodeTraverser = $nodeTraverser;
        $this->metadataFactory = $metadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function startContext($root = null)
    {
        return new ContextualValidator(
            $this->contextFactory->createContext($this, $root),
            $this->nodeTraverser,
            $this->metadataFactory
        );
    }

    /**
     * {@inheritdoc}
     */
    public function inContext(ExecutionContextInterface $context)
    {
        return new ContextualValidator(
            $context,
            $this->nodeTraverser,
            $this->metadataFactory
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($object)
    {
        return $this->metadataFactory->getMetadataFor($object);
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($object)
    {
        return $this->metadataFactory->hasMetadataFor($object);
    }

    public function validate($value, $constraints, $groups = null)
    {
        return $this->startContext($value)
            ->validate($value, $constraints, $groups)
            ->getViolations();
    }

    public function validateObject($object, $groups = null)
    {
        return $this->startContext($object)
            ->validateObject($object, $groups)
            ->getViolations();
    }

    public function validateCollection($collection, $groups = null, $deep = false)
    {
        return $this->startContext($collection)
            ->validateCollection($collection, $groups, $deep)
            ->getViolations();
    }

    public function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->startContext($object)
            ->validateProperty($object, $propertyName, $groups)
            ->getViolations();
    }

    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->startContext($object)
            ->validatePropertyValue($object, $propertyName, $value, $groups)
            ->getViolations();
    }
}
