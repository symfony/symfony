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

use Symfony\Component\Translation\TranslatorInterface;
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

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string|null
     */
    private $translationDomain;

    public function __construct(GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->groupManager = $groupManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->contextStack = new \SplStack();
    }

    public function initialize(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function startContext($root)
    {
        if (null === $this->validator) {
            // TODO error, call initialize() first
        }

        $this->currentContext = new LegacyExecutionContext(
            $root,
            $this->validator,
            $this->groupManager,
            $this->translator,
            $this->translationDomain
        );
        $this->contextStack->push($this->currentContext);

        return $this->currentContext;
    }

    public function stopContext()
    {
        if (0 === count($this->contextStack)) {
            return null;
        }

        // Remove the current context from the stack
        $stoppedContext = $this->contextStack->pop();

        // Adjust the current context to the previous context
        $this->currentContext = count($this->contextStack) > 0
            ? $this->contextStack->top()
            : null;

        return $stoppedContext;
    }

    public function getCurrentContext()
    {
        return $this->currentContext;
    }

    public function enterNode(Node $node)
    {
        if (null === $this->currentContext) {
            // TODO error call startContext() first
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
}
