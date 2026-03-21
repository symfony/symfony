<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Deprecation;

/**
 * Marks objects that can appear in a debug_backtrace() call stack and may expose structured
 * context about an error they are currently dispatching via trigger_error().
 *
 * This interface allows tools such as PHPUnit's IssueTriggerResolver to scan the call stack for
 * a responsible dispatcher and obtain callee/caller information from it, without parsing error
 * message strings or making assumptions about the internal stack frame layout of any particular
 * component.
 *
 * Contract for implementors
 * -------------------------
 * Providing context is optional: getIssueTriggerContext() may return null at any time, including
 * during an active trigger_error() call, when no structured context is available for that error.
 *
 * However, if a non-null context is returned, it must be scoped strictly to the duration of the
 * trigger_error() call that it describes: the context must not be made available before that call,
 * must not persist after it returns, and must not "leak" into other calls or operations that happen
 * to occur while this object is on the call stack for unrelated reasons.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface IssueTriggerContextProvider
{
    /**
     * Returns context about the error currently being dispatched via trigger_error(), or null
     * when this object is not the active dispatcher of an ongoing trigger_error() call.
     *
     * See the interface-level documentation for the precise contract.
     */
    public function getIssueTriggerContext(): ?IssueTriggerContextInterface;
}
