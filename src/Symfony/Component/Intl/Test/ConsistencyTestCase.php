<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Test;

use Symfony\Component\Intl\Intl;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class ConsistencyTestCase extends \PHPUnit_Framework_TestCase
{
    public function provideLocales()
    {
        $parameters = array();

        foreach (Intl::getLocaleBundle()->getLocales() as $locale) {
            $parameters[] = array($locale);
        }

        return $parameters;
    }

    public function provideRootLocales()
    {
        $parameters = array();
        $locales = Intl::getLocaleBundle()->getLocales();
        $aliases = Intl::getLocaleBundle()->getLocaleAliases();

        $locales = array_filter($locales, function ($locale) use ($aliases) {
            // no aliases
            // no locales for which fallback is possible (e.g "en_GB")
            return !isset($aliases[$locale]) && false === strpos($locale, '_');
        });

        foreach ($locales as $locale) {
            $parameters[] = array($locale);
        }

        return $parameters;
    }

    public function provideLocaleAliases()
    {
        $parameters = array();

        foreach (Intl::getLocaleBundle()->getLocaleAliases() as $alias => $ofLocale) {
            $parameters[] = array($alias, $ofLocale);
        }

        return $parameters;
    }
}
