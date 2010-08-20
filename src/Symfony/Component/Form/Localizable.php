<?php

namespace Symfony\Component\Form;

/**
 * Marks classes that you can inject a locale into.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface Localizable
{
    /**
     * Sets the locale of the class.
     *
     * @param string $locale
     */
    public function setLocale($locale);
}