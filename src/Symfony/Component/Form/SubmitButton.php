<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * A button that submits the form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitButton extends Button implements ClickableInterface
{
    private bool $clicked = false;

    public function isClicked(): bool
    {
        return $this->clicked;
    }

    /**
     * Submits data to the button.
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function submit(array|string|null $submittedData, bool $clearMissing = true): static
    {
        if ($this->getConfig()->getDisabled()) {
            $this->clicked = false;

            return $this;
        }

        parent::submit($submittedData, $clearMissing);

        $this->clicked = null !== $submittedData;

        return $this;
    }
}
