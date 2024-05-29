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
 * A builder for {@link SubmitButton} instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitButtonBuilder extends ButtonBuilder
{
    /**
     * Creates the button.
     */
    public function getForm(): SubmitButton
    {
        return new SubmitButton($this->getFormConfig());
    }
}
