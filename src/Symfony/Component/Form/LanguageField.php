<?php

namespace Symfony\Component\Form;

/**
 * A field for selecting from a list of languages
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class LanguageField extends ChoiceField
{
    /**
     * Caches the language choices in different locales
     * @var array
     */
    protected static $languages;

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption('choices', self::getLanguageChoices($this->locale));

        parent::configure();
    }

    /**
     * Returns the list of languages for a locale
     *
     * @param string $locale     The locale to use for the language names
     * @throws RuntimeException  When the resource bundles cannot be loaded
     */
    protected static function getLanguageChoices($locale)
    {
        if (!isset(self::$languages[$locale])) {
            $bundle = new \ResourceBundle($locale, __DIR__.'/Resources/data/lang');

            if ($bundle === null) {
                throw new RuntimeException('The language resource bundle could not be loaded');
            }

            $collator = new \Collator($locale);
            $languages = array();

            foreach ($bundle->get('Languages') as $code => $name) {
                // "mul" is the code for multiple languages
                if ($code !== 'mul') {
                    $languages[$code] = $name;
                }
            }

            $collator->asort($languages);

            self::$languages[$locale] = $languages;
        }

        return self::$languages[$locale];
    }
}