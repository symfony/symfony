<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Component\Locale\Formatter;
use Symfony\Component\Locale\Tests\AbstractFormatterTest;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\LocaleHelper;

class LocaleHelperTest extends AbstractFormatterTest
{
    private $helper;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        parent::setUp();

        $this->helper = new LocaleHelper(new Formatter('USD'));
    }

    protected function tearDown()
    {
        parent::tearDown();

        unset($this->helper);
    }

    public function testFormatCurrency()
    {
        $currency = $this->helper->formatCurrency(100);
        $this->assertEquals('$100.00', $currency);
    }

    public function testFormatDate()
    {
        $date = $this->helper->formatDate($this->dateTime);
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatTime()
    {
        $date = $this->helper->formatTime($this->dateTime);
        $this->assertEquals('11:00 PM', $date);
    }

    public function testFormatDateTime()
    {
        $date = $this->helper->formatDateTime($this->dateTime);
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);
    }
}
