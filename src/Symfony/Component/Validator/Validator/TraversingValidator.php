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
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;

/**
 * Default implementation of {@link ValidatorInterface}.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TraversingValidator implements ValidatorInterface
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

    /**
     * Creates a new validator.
     *
     * @param ExecutionContextFactoryInterface $contextFactory  The factory for
     *                                                          creating new contexts
     * @param NodeTraverserInterface           $nodeTraverser   The node traverser
     * @param MetadataFactoryInterface         $metadataFactory The factory for
     *                                                          fetching the metadata
     *                                                          of validated objects
     */
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
        return new TraversingContextualValidator(
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
        return new TraversingContextualValidator(
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

    /**
     * {@inheritdoc}
     */
    public function validate($value, $constraints = null, $groups = null)
    {
        return $this->startContext($value)
            ->validate($value, $constraints, $groups)
            ->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->startContext($object)
            ->validateProperty($object, $propertyName, $groups)
            ->getViolations();
    }

    /**
     * {@inheritdoc}
     */
    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->startContext($object)
            ->validatePropertyValue($object, $propertyName, $value, $groups)
            ->getViolations();
    }
}
