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
abstract class ToggleField extends Field
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('value');

        parent::configure();

        $this->setValueTransformer(new BooleanToStringTransformer());
    }

    public function isChecked()
    {
        return $this->getData();
    }

    public function getValue()
    {
        return $this->getOption('value');
    }

    public function hasValue()
    {
        return $this->getValue() !== null;
    }
}
