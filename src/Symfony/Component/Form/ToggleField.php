<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

/**
 * An input field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
abstract class ToggleField extends InputField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('value');
        $this->addOption('label');
        $this->addOption('translate_label', false);

        $this->setValueTransformer(new BooleanToStringTransformer());
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        $html = parent::render(array_merge(array(
            'value'     => $this->getOption('value'),
            'checked'	  => ((string)$this->getDisplayedData() !== '' && $this->getDisplayedData() !== 0),
        ), $attributes));

        if ($label = $this->getOption('label')) {
            if ($this->getOption('translate_label')) {
                $label = $this->translate($label);
            }

            $html .= ' '.$this->generator->contentTag('label', $label, array(
                'for' => $this->getId(),
            ));
        }

        return $html;
    }
}
