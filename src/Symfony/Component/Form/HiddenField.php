<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Renderer\InputHiddenRenderer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A hidden field
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class HiddenField extends InputField
{
    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render(array_merge(array(
            'type'    => 'hidden',
        ), $attributes));
    }

    /**
     * {@inheritDoc}
     */
    public function isHidden()
    {
        return true;
    }
}