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
 * A field for selecting from a list of languages.
 *
 * @see Symfony\Component\Form\ChoiceField
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class LanguageField extends ChoiceField
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', Locale::getDisplayLanguages(\Locale::getDefault()));

        parent::configure();
    }
}