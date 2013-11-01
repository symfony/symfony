<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DataProvider;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractDataProviderTest extends \PHPUnit_Framework_TestCase
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

    protected static function getLocaleAliases()
    {
        return array();
    }
}
