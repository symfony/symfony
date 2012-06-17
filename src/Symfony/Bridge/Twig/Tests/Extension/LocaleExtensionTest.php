<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Component\Locale\Formatter;
use Symfony\Component\Locale\Tests\AbstractFormatterTest;
use Symfony\Bridge\Twig\Extension\LocaleExtension;
use Symfony\Bridge\Twig\Tests\TestCase;

class LocaleExtensionTests extends AbstractFormatterTest
{
    private $extension;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        parent::setUp();

        $this->extension = new LocaleExtension(new Formatter('USD'));
    }

    protected function tearDown()
    {
        parent::tearDown();

        unset($this->extension);
    }

    public function testFormatCurrency()
    {
        $currency = $this->extension->formatCurrency(100);
        $this->assertEquals('$100.00', $currency);
    }

    public function testFormatDate()
    {
        $date = $this->extension->formatDate($this->dateTime);
        $this->assertEquals('Jul 10, 2012', $date);
    }

    public function testFormatTime()
    {
        $date = $this->extension->formatTime($this->dateTime);
        $this->assertEquals('11:00 PM', $date);
    }

    public function testFormatDateTime()
    {
        $date = $this->extension->formatDateTime($this->dateTime);
        $this->assertEquals('Jul 10, 2012 11:00 PM', $date);
    }
}
