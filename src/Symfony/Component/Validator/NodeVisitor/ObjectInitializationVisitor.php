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
use Symfony\Component\Validator\Node\ClassNode;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\ObjectInitializerInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ObjectInitializationVisitor extends AbstractVisitor
{
    /**
     * @var ObjectInitializerInterface[]
     */
    private $initializers;

    public function __construct(array $initializers)
    {
        foreach ($initializers as $initializer) {
            if (!$initializer instanceof ObjectInitializerInterface) {
                throw new \InvalidArgumentException('Validator initializers must implement ObjectInitializerInterface.');
            }
        }

        // If no initializer is present, this visitor should not even be created
        if (0 === count($initializers)) {
            throw new \InvalidArgumentException('Please pass at least one initializer.');
        }

        $this->initializers = $initializers;
    }

    public function visit(Node $node, ExecutionContextInterface $context)
    {
        if (!$node instanceof ClassNode) {
            return;
        }

        foreach ($this->initializers as $initializer) {
            $initializer->initialize($node->value);
        }
    }
}
