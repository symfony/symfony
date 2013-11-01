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
    protected function setUp()
    {
        \Locale::setDefault('en');
    }

    public function provideLocales()
    {
        return array_map(
            function ($locale) { return array($locale); },
            $this->getLocales()
        );
    }

    public function provideRootLocales()
    {
        return array_map(
            function ($locale) { return array($locale); },
            $this->getRootLocales()
        );
    }

    public function provideLocaleAliases()
    {
        return array_map(
            function ($alias, $ofLocale) { return array($alias, $ofLocale); },
            array_keys($this->getLocaleAliases()),
            $this->getLocaleAliases()
        );
    }

    protected static function getLocales()
    {
        return array();
    }

    protected static function getRootLocales()
    {
        return array_filter(static::getLocales(), function ($locale) {
            // no locales for which fallback is possible (e.g "en_GB")
            return false === strpos($locale, '_');
        });
    }

    protected static function getLocaleAliases()
    {
        return array();
    }
}
