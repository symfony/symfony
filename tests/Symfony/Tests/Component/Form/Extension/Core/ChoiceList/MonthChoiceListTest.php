<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\MonthChoiceList;

class MonthChoiceListTest extends \PHPUnit_Framework_TestCase
{
    private $formatter;

    protected function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The "intl" extension is not available');
        }

        \Locale::setDefault('en');

        // I would prefer to mock the formatter, but this leads to weird bugs
        // with the current version of PHPUnit
        $this->formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE,
            'UTC'
        );
    }

    protected function tearDown()
    {
        $this->formatter = null;
    }

    public function testNumericMonthsIfPatternContainsNoMonth()
    {
        $this->formatter->setPattern('yy');

        $months = array(1, 4);
        $list = new MonthChoiceList($this->formatter, $months);

        $names = array(1 => '01', 4 => '04');
        $this->assertSame($names, $list->getChoices());
    }

    public function testFormattedMonthsShort()
    {
        $this->formatter->setPattern('dd.MMM.yy');

        $months = array(1, 4);
        $list = new MonthChoiceList($this->formatter, $months);

        $names = array(1 => 'Jan', 4 => 'Apr');
        $this->assertSame($names, $list->getChoices());
    }

    public function testFormattedMonthsLong()
    {
        $this->formatter->setPattern('dd.MMMM.yy');

        $months = array(1, 4);
        $list = new MonthChoiceList($this->formatter, $months);

        $names = array(1 => 'January', 4 => 'April');
        $this->assertSame($names, $list->getChoices());
    }

    public function testFormattedMonthsLongWithDifferentTimezone()
    {
        $this->formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE,
            'PST'
        );

        $this->formatter->setPattern('dd.MMMM.yy');

        $months = array(1, 4);
        $list = new MonthChoiceList($this->formatter, $months);

        $names = array(1 => 'January', 4 => 'April');
        // uses UTC internally
        $this->assertSame($names, $list->getChoices());
        $this->assertSame('PST', $this->formatter->getTimezoneId());
    }
}
