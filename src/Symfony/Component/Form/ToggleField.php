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