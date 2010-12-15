<?php

namespace Symfony\Component\Form;

/**
 * A field for selecting from a list of countries
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class CountryField extends ChoiceField
{
    /**
     * Caches the country choices in different locales
     * @var array
     */
    protected static $countries;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', self::getCountryChoices($this->locale));

        parent::configure();
    }

    /**
     * Returns the list of countries for a locale
     *
     * @param string $locale     The locale to use for the country names
     * @throws RuntimeException  When the resource bundles cannot be loaded
     */
    protected static function getCountryChoices($locale)
    {
        if (!isset(self::$countries[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/region');

            if ($bundle === null) {
                throw new RuntimeException('The region resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $countries = array();

            foreach ($bundle->get('Countries') as $code => $name) {
                // Global regions (f.i. "America") have numeric codes
                // Countries have alphabetic codes
                // "ZZ" is the code for unknown region
                if (ctype_alpha($code) && $code !== 'ZZ') {
                    $countries[$code] = $name;
                }
            }

            $collator->asort($countries);

            self::$countries[$locale] = $countries;
        }

        return self::$countries[$locale];
    }
}