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

use Symfony\Component\Form\ValueTransformer\PercentToLocalizedStringTransformer;

/**
 * A localized field for entering percentage values.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class PercentField extends NumberField
{
    const FRACTIONAL = 'fractional';
    const INTEGER = 'integer';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', 0);
        $this->addOption('percent_type', self::FRACTIONAL);

        parent::configure();

        $this->setValueTransformer(new PercentToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'type' => $this->getOption('percent_type'),
        )));
    }
}
