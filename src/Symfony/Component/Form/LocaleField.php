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

use Symfony\Component\Locale\Locale;

/**
 * A field for selecting from a list of locales.
 *
 * @see Symfony\Component\Form\ChoiceField
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class LocaleField extends ChoiceField
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', Locale::getDisplayLocales(\Locale::getDefault()));

        parent::configure();
    }
}