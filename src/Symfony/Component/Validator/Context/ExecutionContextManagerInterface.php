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

/**
 * Manages the creation and deletion of {@link ExecutionContextInterface}
 * instances.
 *
 * Start a new context with {@link startContext()}. You can retrieve the context
 * with {@link getCurrentContext()} and stop it again with {@link stopContext()}.
 *
 *     $contextManager->startContext();
 *     $context = $contextManager->getCurrentContext();
 *     $contextManager->stopContext();
 *
 * You can also start several nested contexts. The {@link getCurrentContext()}
 * method will always return the most recently started context.
 *
 *     // Start context 1
 *     $contextManager->startContext();
 *
 *     // Start context 2
 *     $contextManager->startContext();
 *
 *     // Returns context 2
 *     $context = $contextManager->getCurrentContext();
 *
 *     // Stop context 2
 *     $contextManager->stopContext();
 *
 *     // Returns context 1
 *     $context = $contextManager->getCurrentContext();
 *
 * See also {@link ExecutionContextInterface} for more information.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see ExecutionContextInterface
 */
interface ExecutionContextManagerInterface
{
    /**
     * Starts a new context.
     *
     * The newly started context is returned. You can subsequently access the
     * context with {@link getCurrentContext()}.
     *
     * @param mixed $root The root value of the object graph in the new context
     *
     * @return ExecutionContextInterface The started context
     */
    public function startContext($root);

    /**
     * Stops the current context.
     *
     * If multiple contexts have been started, the most recently started context
     * is stopped. The stopped context is returned from this method.
     *
     * After calling this method, {@link getCurrentContext()} will return the
     * context that was started before the stopped context.
     *
     * @return ExecutionContextInterface The stopped context
     */
    public function stopContext();

    /**
     * Returns the current context.
     *
     * If multiple contexts have been started, the current context refers to the
     * most recently started context.
     *
     * @return ExecutionContextInterface The current context
     */
    public function getCurrentContext();
}
