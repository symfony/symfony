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
 * Carries context regarding caller and callee for errors that are currently being dispatched via trigger_error().
 *
 * Instances of this interface are returned by {@see IssueTriggerContextProvider::getIssueTriggerContext()}
 * while a trigger_error() call initiated by the provider is executing. They allow tools such as
 * PHPUnit's IssueTriggerResolver to determine the semantic origin of the error (callee and caller
 * file paths) without parsing message strings or making assumptions about the internal stack layout
 * of the dispatching component.
 *
 * Callee and caller are defined from the perspective of the error semantics, not the call stack:
 *   - The callee is the file that contains or defines the deprecated or restricted construct
 *   - The caller is the file that uses, invokes or activates that construct
 *
 * Either can be null when the information is not available for a particular error.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
interface IssueTriggerContextInterface
{
    /**
     * Returns the path of the file that defines the deprecated or restricted construct.
     *
     * For example, when a class extends a deprecated parent, this is the file containing the
     * deprecated parent class definition. Returns null if this information is not available.
     */
    public function getCalleeFile(): ?string;

    /**
     * Returns the path of the file that uses the deprecated or restricted construct.
     *
     * For example, when a class extends a deprecated parent, this is the file containing the
     * child class definition. Returns null if this information is not available.
     */
    public function getCallerFile(): ?string;
}
