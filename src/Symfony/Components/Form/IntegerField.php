<?php

namespace Symfony\Components\Form;

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