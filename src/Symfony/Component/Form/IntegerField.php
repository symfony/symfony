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

use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A localized field for entering integers.
 *
 * The rounding-mode option defaults to rounding down. The available values are:
 *  * NumberToLocalizedStringTransformer::ROUND_DOWN
 *  * NumberToLocalizedStringTransformer::ROUND_UP
 *  * NumberToLocalizedStringTransformer::ROUND_FLOOR
 *  * NumberToLocalizedStringTransformer::ROUND_CEILING
 *
 * @see Symfony\Component\Form\NumberField
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class IntegerField extends NumberField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('precision', 0);

        // Integer cast rounds towards 0, so do the same when displaying fractions
        $this->addOption('rounding-mode', NumberToLocalizedStringTransformer::ROUND_DOWN);

        parent::configure();
    }

    /**
     * {@inheritDoc}
     */
    public function getData()
    {
        return (int)parent::getData();
    }
}