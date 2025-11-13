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

use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface ButtonFlowInterface extends FormInterface, ClickableInterface
{
    /**
     * Executes the callable handler.
     */
    public function handle(): void;

    /**
     * Checks if the callable handler was already called.
     */
    public function isHandled(): bool;

    /**
     * Checks if the button's action is 'reset'.
     */
    public function isResetAction(): bool;

    /**
     * Checks if the button's action is 'previous'.
     */
    public function isPreviousAction(): bool;

    /**
     * Checks if the button's action is 'next'.
     */
    public function isNextAction(): bool;

    /**
     * Checks if the button's action is 'finish'.
     */
    public function isFinishAction(): bool;

    /**
     * Checks if the button is configured to clear submission data.
     */
    public function isClearSubmission(): bool;
}
