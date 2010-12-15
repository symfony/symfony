<?php

namespace Symfony\Component\Form;

use Symfony\Component\Locale\Locale;

/**
 * A field for selecting from a list of countries
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class CountryField extends ChoiceField
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', Locale::getDisplayCountries($this->locale));

        parent::configure();
    }
}