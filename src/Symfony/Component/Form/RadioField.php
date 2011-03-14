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
 * A radio field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class RadioField extends ToggleField
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        // TESTME
        return $this->getParent() ? $this->getParent()->getName() : parent::getName();
    }
}
