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

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;

/**
 * An input field for selecting boolean values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
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
