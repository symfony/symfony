<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form;

/**
 * A builder for {@link SubmitButton} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitButtonBuilder extends ButtonBuilder
{
    /**
     * Creates the button.
     *
     * @return SubmitButton The button
     */
    public function getForm()
    {
        return new SubmitButton($this->getFormConfig());
    }
}
