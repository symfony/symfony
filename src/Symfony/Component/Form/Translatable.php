<?php

namespace Symfony\Component\Form;

use Symfony\Component\I18N\TranslatorInterface;

/**
 * Marks classes that you can inject a translator into.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface Translatable
{
    /**
     * Sets the translator unit of the class.
     *
     * @param TranslatorInterface $translator
     */
    public function setTranslator(TranslatorInterface $translator);
}
