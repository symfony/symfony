<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use Symfony\Component\Intl\Locale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Locale::setDefault('en');
    }

    public function testGetDisplayName()
    {
        $this->assertSame('English', Locale::getDisplayName('en', 'en'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNameFailsOnInvalidLocale()
    {
        Locale::getDisplayName('foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNameFailsOnInvalidDisplayLocale()
    {
        Locale::getDisplayName('en', 'foo');
    }

    public function testGetDisplayNames()
    {
        $names = Locale::getDisplayNames('en');

        $this->assertArrayHasKey('en', $names);
        $this->assertSame('English', $names['en']);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNamesFailsOnInvalidDisplayLocale()
    {
        Locale::getDisplayNames('foo');
    }
}
