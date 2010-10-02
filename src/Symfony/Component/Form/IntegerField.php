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

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A localized field for entering integers.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
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