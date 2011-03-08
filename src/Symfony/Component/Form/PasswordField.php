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
 * A field for entering a password.
 *
 * Available options:
 *
 *  * always_empty      If true, the field will always render empty. Default: true.
 *
 * @see Symfony\Component\Form\TextField
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PasswordField extends TextField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('always_empty', true);

        parent::configure();
    }

    /**
     * {@inheritDoc}
     */
    public function getDisplayedData()
    {
        return $this->getOption('always_empty') || !$this->isSubmitted()
                ? ''
                : parent::getDisplayedData();
    }
}