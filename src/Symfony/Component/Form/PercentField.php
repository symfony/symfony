<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\PercentToLocalizedStringTransformer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $this->addOption('type', self::FRACTIONAL);

        $this->setValueTransformer(new PercentToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'type' => $this->getOption('type'),
        )));
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render($attributes).' %';
    }
}