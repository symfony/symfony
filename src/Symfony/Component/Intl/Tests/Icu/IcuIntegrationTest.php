<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tests\Intl\Icu;

use Symfony\Component\Intl\Icu\IcuCurrencyBundle;
use Symfony\Component\Intl\Icu\IcuLanguageBundle;
use Symfony\Component\Intl\Icu\IcuLocaleBundle;
use Symfony\Component\Intl\Icu\IcuRegionBundle;
use Symfony\Component\Intl\ResourceBundle\Reader\PhpBundleReader;
use Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReader;

/**
 * Verifies that the data files can actually be read.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuIntegrationTest extends \PHPUnit_Framework_TestCase
{
    public function testCurrencyBundle()
    {
        $bundle = new IcuCurrencyBundle(new StructuredBundleReader(new PhpBundleReader()));

        $this->assertSame('€', $bundle->getCurrencySymbol('EUR', 'en'));
        $this->assertSame(array('en'), $bundle->getLocales());
    }

    public function testLanguageBundle()
    {
        $bundle = new IcuLanguageBundle(new StructuredBundleReader(new PhpBundleReader()));

        $this->assertSame('German', $bundle->getLanguageName('de', null, 'en'));
        $this->assertSame(array('en'), $bundle->getLocales());
    }

    public function testLocaleBundle()
    {
        $bundle = new IcuLocaleBundle(new StructuredBundleReader(new PhpBundleReader()));

        $this->assertSame('Azerbaijani', $bundle->getLocaleName('az', 'en'));
        $this->assertSame(array('en'), $bundle->getLocales());
    }

    public function testRegionBundle()
    {
        $bundle = new IcuRegionBundle(new StructuredBundleReader(new PhpBundleReader()));

        $this->assertSame('United Kingdom', $bundle->getCountryName('GB', 'en'));
        $this->assertSame(array('en'), $bundle->getLocales());
    }
}
