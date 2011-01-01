<?php

namespace Symfony\Component\Form;

use Symfony\Component\Locale\Locale;

/**
 * A field for selecting from a list of locales
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class LocaleField extends ChoiceField
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', Locale::getDisplayLocales($this->locale));

        parent::configure();
    }
}