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
    private $clicked = false;

    /**
     * {@inheritdoc}
     */
    public function isClicked()
    {
        return $this->clicked;
    }

    /**
     * Submits data to the button.
     *
     * @param array|string|null $submittedData The data
     * @param bool              $clearMissing  Not used
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function submit($submittedData, bool $clearMissing = true)
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
