<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * A hidden field
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class HiddenField extends Field
{
    /**
     * {@inheritDoc}
     */
    public function isHidden()
    {
        return true;
    }
}