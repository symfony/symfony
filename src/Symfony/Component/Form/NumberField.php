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

use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;

/**
 * A localized field for entering numbers.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class NumberField extends Field
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        // default precision is locale specific (usually around 3)
        $this->addOption('precision');
        $this->addOption('grouping', false);
        $this->addOption('rounding-mode', NumberToLocalizedStringTransformer::ROUND_HALFUP);

        parent::configure();

        $this->setValueTransformer(new NumberToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'grouping' => $this->getOption('grouping'),
            'rounding-mode' => $this->getOption('rounding-mode'),
        )));
    }
}
