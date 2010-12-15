<?php

namespace Symfony\Component\Form;

use Symfony\Component\Locale\Locale;

/**
 * A field for selecting from a list of languages
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class LanguageField extends ChoiceField
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', Locale::getDisplayLanguages($this->locale));

        parent::configure();
    }
}