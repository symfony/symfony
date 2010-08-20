<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Renderer\InputTextRenderer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A text input field.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TextField extends InputField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('max_length');
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render(array_merge(array(
            'type'        => 'text',
            'maxlength'   => $this->getOption('max_length'),
        ), $attributes));
    }
}