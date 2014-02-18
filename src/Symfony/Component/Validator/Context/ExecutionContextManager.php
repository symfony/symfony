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

        if (null !== $this->currentContext) {
            $this->contextStack->push($this->currentContext);
        }

        $this->currentContext = new LegacyExecutionContext(
            $root,
            $this->validator,
            $this->groupManager,
            $this->translator,
            $this->translationDomain
        );

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
        $this->contextStack = new \SplStack();
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
