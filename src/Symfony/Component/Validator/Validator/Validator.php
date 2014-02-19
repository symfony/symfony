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

use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Context\ExecutionContextManagerInterface;
use Symfony\Component\Validator\NodeTraverser\NodeTraverserInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Validator extends AbstractValidator
{
    /**
     * @var ExecutionContextManagerInterface
     */
    protected $contextManager;

    public function __construct(NodeTraverserInterface $nodeTraverser, MetadataFactoryInterface $metadataFactory, ExecutionContextManagerInterface $contextManager)
    {
        parent::__construct($nodeTraverser, $metadataFactory);

        $this->contextManager = $contextManager;
    }

    public function validate($value, $constraints, $groups = null)
    {
        $this->contextManager->startContext($value);

        $this->traverse($value, $constraints, $groups);

        return $this->contextManager->stopContext()->getViolations();
    }

    public function validateObject($object, $groups = null)
    {
        $this->contextManager->startContext($object);

        $this->traverseObject($object, $groups);

        return $this->contextManager->stopContext()->getViolations();
    }

    public function validateCollection($collection, $groups = null, $deep = false)
    {
        $this->contextManager->startContext($collection);

        $constraint = new Traverse(array(
            'traverse' => true,
            'deep' => $deep,
        ));

        $this->traverse($collection, $constraint, $groups);

        return $this->contextManager->stopContext()->getViolations();
    }

    public function validateProperty($object, $propertyName, $groups = null)
    {
        $this->contextManager->startContext($object);

        $this->traverseProperty($object, $propertyName, $groups);

        return $this->contextManager->stopContext()->getViolations();
    }

    public function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        $this->contextManager->startContext($object);

        $this->traversePropertyValue($object, $propertyName, $value, $groups);

        return $this->contextManager->stopContext()->getViolations();
    }
}
