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
