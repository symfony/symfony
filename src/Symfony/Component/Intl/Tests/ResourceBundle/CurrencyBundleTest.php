<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle;

use Symfony\Component\Intl\ResourceBundle\CurrencyBundle;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CurrencyBundleTest extends \PHPUnit_Framework_TestCase
{
    const RES_DIR = '/base/curr';

    /**
     * @var CurrencyBundle
     */
    private $bundle;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    protected function setUp()
    {
        $this->reader = $this->getMock('Symfony\Component\Intl\ResourceBundle\Reader\StructuredBundleReaderInterface');
        $this->bundle = new CurrencyBundle(self::RES_DIR, $this->reader);
    }

    public function testGetCurrencySymbol()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Currencies', 'EUR', 1))
            ->will($this->returnValue('€'));

        $this->assertSame('€', $this->bundle->getCurrencySymbol('EUR', 'en'));
    }

    public function testGetCurrencyName()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Currencies', 'EUR', 0))
            ->will($this->returnValue('Euro'));

        $this->assertSame('Euro', $this->bundle->getCurrencyName('EUR', 'en'));
    }

    public function testGetCurrencyNames()
    {
        $sortedCurrencies = array(
            'USD' => array(0 => 'Dollar'),
            'EUR' => array(0 => 'Euro'),
        );

        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Currencies'))
            ->will($this->returnValue($sortedCurrencies));

        $sortedNames = array(
            'USD' => 'Dollar',
            'EUR' => 'Euro',
        );

        $this->assertSame($sortedNames, $this->bundle->getCurrencyNames('en'));
    }

    public function testGetFractionDigits()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Currencies', 'EUR', 2))
            ->will($this->returnValue(123));

        $this->assertSame(123, $this->bundle->getFractionDigits('EUR'));
    }

    public function testGetRoundingIncrement()
    {
        $this->reader->expects($this->once())
            ->method('readEntry')
            ->with(self::RES_DIR, 'en', array('Currencies', 'EUR', 3))
            ->will($this->returnValue(123));

        $this->assertSame(123, $this->bundle->getRoundingIncrement('EUR'));
    }
}
