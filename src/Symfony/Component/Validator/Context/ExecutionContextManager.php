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

use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\NodeVisitor\AbstractVisitor;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ExecutionContextManager extends AbstractVisitor implements ExecutionContextManagerInterface
{
    /**
     * @var GroupManagerInterface
     */
    private $groupManager;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ExecutionContext
     */
    private $currentContext;

    /**
     * @var \SplStack|ExecutionContext[]
     */
    private $contextStack;

    public function __construct(GroupManagerInterface $groupManager)
    {
        $this->groupManager = $groupManager;

        $this->reset();
    }

    public function initialize(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function startContext()
    {
        if (null !== $this->currentContext) {
            $this->contextStack->push($this->currentContext);
        }

        $this->currentContext = new ExecutionContext($this->validator, $this->groupManager);

        return $this->currentContext;
    }

    public function stopContext()
    {
        $stoppedContext = $this->currentContext;

        if (0 === count($this->contextStack)) {
            $this->currentContext = null;

            return $stoppedContext;
        }

        if (1 === count($this->contextStack)) {
            $this->contextStack->pop();
            $this->currentContext = null;

            return $stoppedContext;
        }

        $this->contextStack->pop();
        $this->currentContext = $this->contextStack->top();

        return $stoppedContext;
    }

    public function getCurrentContext()
    {
        return $this->currentContext;
    }

    public function afterTraversal(array $nodes)
    {
        $this->reset();
    }

    public function enterNode(Node $node)
    {
        if (null === $this->currentContext) {
            // error no context started
        }

        $this->currentContext->pushNode($node);
    }

    public function leaveNode(Node $node)
    {
        if (null === $this->currentContext) {
            // error no context started
        }

        $this->currentContext->popNode();
    }

    private function reset()
    {
        $this->contextStack = new \SplStack();
    }
}
