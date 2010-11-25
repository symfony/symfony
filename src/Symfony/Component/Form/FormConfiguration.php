<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FormConfiguration holds the default configuration for forms (CSRF, locale, ...).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormConfiguration
{
    protected static $defaultCsrfSecret = null;
    protected static $defaultCsrfProtection = false;
    protected static $defaultCsrfFieldName = '_token';

    protected static $defaultLocale = null;

    /**
     * Sets the default locale for newly created forms.
     *
     * @param string $defaultLocale
     */
    static public function setDefaultLocale($defaultLocale)
    {
        self::$defaultLocale = $defaultLocale;
    }

    /**
     * Returns the default locale for newly created forms.
     *
     * @return string
     */
    static public function getDefaultLocale()
    {
        return self::$defaultLocale;
    }

    /**
     * Enables CSRF protection for all new forms
     */
    static public function enableDefaultCsrfProtection()
    {
        self::$defaultCsrfProtection = true;
    }

    /**
     * Checks if Csrf protection for all forms is enabled.
     */
    static public function isDefaultCsrfProtectionEnabled()
    {
        return self::$defaultCsrfProtection;
    }

    /**
     * Disables Csrf protection for all forms.
     */
    static public function disableDefaultCsrfProtection()
    {
        self::$defaultCsrfProtection = false;
    }

    /**
     * Sets the CSRF field name used in all new CSRF protected forms
     *
     * @param string $name The CSRF field name
     */
    static public function setDefaultCsrfFieldName($name)
    {
        self::$defaultCsrfFieldName = $name;
    }

    /**
     * Returns the default CSRF field name
     *
     * @return string The CSRF field name
     */
    static public function getDefaultCsrfFieldName()
    {
        return self::$defaultCsrfFieldName;
    }

    /**
     * Sets the CSRF secret used in all new CSRF protected forms
     *
     * @param string $secret
     */
    static public function setDefaultCsrfSecret($secret)
    {
        self::$defaultCsrfSecret = $secret;
    }

    /**
     * Returns the default CSRF secret
     *
     * @return string
     */
    static public function getDefaultCsrfSecret()
    {
        return self::$defaultCsrfSecret;
    }
}
