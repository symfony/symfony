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
 * A checkbox field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class CheckboxField extends ToggleField
{
    /**
     * Available options:
     *
     *  * value:    The value of the input checkbox. If the checkbox is checked,
     *              this value will be posted as the value of the field.
     */
    protected function configure()
    {
        $this->addOption('value', '1');

        parent::configure();
    }
}