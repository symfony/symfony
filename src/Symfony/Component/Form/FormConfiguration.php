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
 * FormConfiguration holds the default configuration for forms (CSRF, locale, ...).
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormConfiguration
{
    protected static $defaultCsrfSecrets = array();
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
        return isset(self::$defaultLocale) ? self::$defaultLocale : \Locale::getDefault();
    }

    /**
     * Enables CSRF protection for all new forms.
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
     * Sets the CSRF field name used in all new CSRF protected forms.
     *
     * @param string $name The CSRF field name
     */
    static public function setDefaultCsrfFieldName($name)
    {
        self::$defaultCsrfFieldName = $name;
    }

    /**
     * Returns the default CSRF field name.
     *
     * @return string The CSRF field name
     */
    static public function getDefaultCsrfFieldName()
    {
        return self::$defaultCsrfFieldName;
    }

    /**
     * Sets the default CSRF secrets to be used in all new CSRF protected forms.
     *
     * @param array $secrets
     */
    static public function setDefaultCsrfSecrets(array $secrets)
    {
        self::$defaultCsrfSecrets = $secrets;
    }

    /**
     * Adds CSRF secrets to be used in all new CSRF protected forms.
     *
     * @param string $secret
     */
    static public function addDefaultCsrfSecret($secret)
    {
        self::$defaultCsrfSecrets[] = $secret;
    }

    /**
     * Clears the default CSRF secrets.
     */
    static public function clearDefaultCsrfSecrets()
    {
        self::$defaultCsrfSecrets = array();
    }

    /**
     * Returns the default CSRF secrets.
     *
     * @return array
     */
    static public function getDefaultCsrfSecrets()
    {
        return self::$defaultCsrfSecrets;
    }
}
