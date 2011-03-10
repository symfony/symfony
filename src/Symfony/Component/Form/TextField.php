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

/**
 * A text input field.
 *
 * Available options:
 *
 *  * max_length:       The max_length to give the field.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class TextField extends Field
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('max_length');

        parent::configure();
    }

    public function getMaxLength()
    {
        return $this->getOption('max_length');
    }
}
