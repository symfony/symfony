<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    function setLocale($locale);
}