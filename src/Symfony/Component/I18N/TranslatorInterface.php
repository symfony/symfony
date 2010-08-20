<?php

namespace Symfony\Component\I18N;

/**
 * A translator used for translating text.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface TranslatorInterface
{
    /**
     * Translates a given text string.
     *
     * @param string $text        The text to translate
     * @param array  $parameters  The parameters to inject into the text
     * @param string $locale      The locale of the translated text. If null,
     *                            the preconfigured locale of the translator
     *                            or the system's default culture is used.
     */
    public function translate($text, array $parameters = array(), $locale = null);
}