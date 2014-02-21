<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\NodeVisitor;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\ObjectInitializerInterface;

/**
 * Initializes the objects of all class nodes.
 *
 * You have to pass at least one instance of {@link ObjectInitializerInterface}
 * to the constructor of this visitor.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ObjectInitializationVisitor extends AbstractVisitor
{
    /**
     * @var ObjectInitializerInterface[]
     */
    private $initializers;

    /**
     * Creates a new visitor.
     *
     * @param ObjectInitializerInterface[] $initializers The object initializers
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $initializers)
    {
        foreach ($initializers as $initializer) {
            if (!$initializer instanceof ObjectInitializerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Validator initializers must implement '.
                    '"Symfony\Component\Validator\ObjectInitializerInterface". '.
                    'Got: "%s"',
                    is_object($initializer) ? get_class($initializer) : gettype($initializer)
                ));
            }
        }

        // If no initializer is present, this visitor should not even be created
        if (0 === count($initializers)) {
            throw new InvalidArgumentException('Please pass at least one initializer.');
        }

        $this->initializers = $initializers;
    }

    /**
     * Calls the {@link ObjectInitializerInterface::initialize()} method for
     * the object of each class node.
     *
     * @param Node                      $node    The current node
     * @param ExecutionContextInterface $context The execution context
     *
     * @return Boolean Always returns true
     */
    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if ($node instanceof ClassNode) {
            foreach ($this->initializers as $initializer) {
                $initializer->initialize($node->value);
            }
        }

        return true;
    }
}
