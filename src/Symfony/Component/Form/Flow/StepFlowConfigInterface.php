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
interface StepFlowConfigInterface
{
    /**
     * Returns the name of the step.
     */
    public function getName(): string;

    /**
     * Returns the closure that determines if the step should be skipped.
     */
    public function getSkip(): ?\Closure;

    /**
     * Determines if the step should be skipped based on the provided data.
     */
    public function isSkipped(mixed $data): bool;
}
