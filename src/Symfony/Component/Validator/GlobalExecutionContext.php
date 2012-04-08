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

use Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface;

/**
 * Stores the node-independent information of a validation run.
 *
 * This class is immutable by design, except for violation tracking.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class GlobalExecutionContext
{
    private $root;
    private $graphWalker;
    private $metadataFactory;
    private $violations;

    public function __construct($root, GraphWalker $graphWalker, ClassMetadataFactoryInterface $metadataFactory)
    {
        $this->root = $root;
        $this->graphWalker = $graphWalker;
        $this->metadataFactory = $metadataFactory;
        $this->violations = new ConstraintViolationList();
    }

    public function __clone()
    {
        $this->violations = clone $this->violations;
    }

    public function addViolation(ConstraintViolation $violation)
    {
        $this->violations->add($violation);
    }

    /**
     * @return ConstraintViolationList
     */
    public function getViolations()
    {
        return $this->violations;
    }

    public function getRoot()
    {
        return $this->root;
    }

    /**
     * @return GraphWalker
     */
    public function getGraphWalker()
    {
        return $this->graphWalker;
    }

    /**
     * @return ClassMetadataFactoryInterface
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }
}
