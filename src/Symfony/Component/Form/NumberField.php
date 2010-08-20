<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Renderer\InputTextRenderer;
use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A localized field for entering numbers.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class NumberField extends InputField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        // default precision is locale specific (usually around 3)
        $this->addOption('precision');
        $this->addOption('grouping', false);

        $this->setValueTransformer(new NumberToLocalizedStringTransformer(array(
            'precision' => $this->getOption('precision'),
            'grouping' => $this->getOption('grouping'),
        )));
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        return parent::render(array_merge(array(
            'type'  => 'text',
        ), $attributes));
    }
}