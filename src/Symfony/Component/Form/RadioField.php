<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Renderer\InputCheckboxRenderer;
use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A radio field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class RadioField extends ToggleField
{
    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render(array_merge(array(
            'type' => 'radio',
            'name' => $this->getParent() ? $this->getParent()->getName() : $this->getName(),
        ), $attributes));
    }
}