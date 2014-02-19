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
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Group\GroupManagerInterface;
use Symfony\Component\Validator\Node\Node;
use Symfony\Component\Validator\NodeVisitor\AbstractVisitor;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The default implementation of {@link ExecutionContextManagerInterface}.
 *
 * This class implements {@link \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface}
 * and updates the current context with the current node of the validation
 * traversal.
 *
 * After creating a new instance, the method {@link initialize()} must be
 * called with a {@link ValidatorInterface} instance. Calling methods such as
 * {@link startContext()} or {@link enterNode()} without initializing the
 * manager first will lead to errors.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextManagerInterface
 * @see \Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface
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

    /**
     * Creates a new context manager.
     *
     * @param GroupManagerInterface $groupManager      The manager for accessing
     *                                                 the currently validated
     *                                                 group
     * @param TranslatorInterface   $translator        The translator
     * @param string|null           $translationDomain The translation domain to
     *                                                 use for translating
     *                                                 violation messages
     */
    public function __construct(GroupManagerInterface $groupManager, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->groupManager = $groupManager;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->contextStack = new \SplStack();
    }

    /**
     * Initializes the manager with a validator.
     *
     * @param ValidatorInterface $validator The validator
     */
    public function initialize(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If {@link initialize()} wasn't called
     */
    public function startContext($root)
    {
        if (null === $this->validator) {
            throw new RuntimeException(
                'initialize() must be called before startContext().'
            );
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

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If {@link startContext()} wasn't called
     */
    public function stopContext()
    {
        if (0 === count($this->contextStack)) {
            throw new RuntimeException(
                'No context was started yet. Call startContext() before '.
                'stopContext().'
            );
        }

        // Remove the current context from the stack
        $stoppedContext = $this->contextStack->pop();

        // Adjust the current context to the previous context
        $this->currentContext = count($this->contextStack) > 0
            ? $this->contextStack->top()
            : null;

        return $stoppedContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentContext()
    {
        return $this->currentContext;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If {@link initialize()} wasn't called
     */
    public function enterNode(Node $node)
    {
        if (null === $this->currentContext) {
            throw new RuntimeException(
                'No context was started yet. Call startContext() before '.
                'enterNode().'
            );
        }

        $this->currentContext->pushNode($node);
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException If {@link initialize()} wasn't called
     */
    public function leaveNode(Node $node)
    {
        if (null === $this->currentContext) {
            throw new RuntimeException(
                'No context was started yet. Call startContext() before '.
                'leaveNode().'
            );
        }

        $this->currentContext->popNode();
    }
}
