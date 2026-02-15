<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface StepFlowBuilderConfigInterface extends StepFlowConfigInterface
{
    /**
     * Returns the form type class name for the step.
     */
    public function getType(): string;

    /**
     * Returns the form options for the step.
     */
    public function getOptions(): array;

    /**
     * Returns the priority of the step.
     */
    public function getPriority(): int;

    /**
     * Sets the priority of the step.
     */
    public function setPriority(int $priority): static;

    /**
     * Sets the closure that determines if the step should be skipped.
     */
    public function setSkip(?\Closure $skip): static;

    /**
     * Returns a StepFlowConfigInterface instance for the step.
     */
    public function getStepConfig(): StepFlowConfigInterface;
}
