<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Comparator;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Comparator\DateComparator;

class DateComparatorTest extends TestCase
{
    /**
     * @dataProvider getConstructorTestData
     */
    public function testConstructor($successes, $failures)
    {
        foreach ($successes as $s) {
            new DateComparator($s);
        }

        foreach ($failures as $f) {
            try {
                new DateComparator($f);
                $this->fail('__construct() throws an \InvalidArgumentException if the test expression is not valid.');
            } catch (\Exception $e) {
                $this->assertInstanceOf('InvalidArgumentException', $e, '__construct() throws an \InvalidArgumentException if the test expression is not valid.');
            }
        }
    }

    /**
     * @dataProvider getTestData
     */
    public function testTest($test, $match, $noMatch)
    {
        $c = new DateComparator($test);

        foreach ($match as $m) {
            $this->assertTrue($c->test($m), '->test() tests a string against the expression');
        }

        foreach ($noMatch as $m) {
            $this->assertFalse($c->test($m), '->test() tests a string against the expression');
        }
    }

    public function getConstructorTestData()
    {
        return array(
            array(
                array(
                    'after2005-10-10', 'until 2005-10-10', 'before 2005-10-10',
                    '>2005-10-10', 'after 20051010', 'since 20051010 21:09:30',
                    '!= 2005/10/10'
                ),
                array(
                    false, null, '', 'foobar',
                    '=2005-10-10', '===2005-10-10'
                ),
            ),
        );
    }

    public function getTestData()
    {
        return array(
            array('< 2005-10-10', array(strtotime('2005-10-09')), array(strtotime('2005-10-15'))),
            array('until 2005-10-10', array(strtotime('2005-10-09')), array(strtotime('2005-10-15'))),
            array('before 2005-10-10', array(strtotime('2005-10-09')), array(strtotime('2005-10-15'))),
            array('> 2005-10-10', array(strtotime('2005-10-15')), array(strtotime('2005-10-09'))),
            array('after 2005-10-10', array(strtotime('2005-10-15')), array(strtotime('2005-10-09'))),
            array('since 2005-10-10', array(strtotime('2005-10-15')), array(strtotime('2005-10-09'))),
            array('!= 2005-10-10', array(strtotime('2005-10-11')), array(strtotime('2005-10-10'))),
            array('< 2005-10-10 10:00', array(strtotime('2005-10-10 09:59:59')), array(strtotime('2005-10-10 10:01'))),
            array('before 20051010124902', array(strtotime('2005/10/10 09:48')), array(strtotime('2005/10/10 13:01'))),
            array(
                array('== 2017-9-1 20:04:30', 'Asia/Shanghai'),
                array(array(strtotime('2017-9-1 12:04:30'), 'GMT')),
                array(array(strtotime('2017-9-1 12:04:30'), 'America/New_York'))
            ),
            array(
                array('after 2017-9-1 20:04:30', new DateTimeZone('Asia/Shanghai')),
                array(array(strtotime('2017-9-1 12:04:31'), 'GMT'), array(strtotime('2017-9-1 08:04:31'), 'America/New_York')),
                array(array(strtotime('2017-9-1 12:04:30'), new DateTimeZone('GMT')), array(strtotime('2017-9-1 08:04:30'), 'America/New_York')),
            ),
            array(
                array('before 2010-10-10', new DateTimeZone('Asia/Shanghai')),
                array(array(strtotime('2010-10-09 15:59:59'), 'GMT'), array(strtotime('2010-10-09 11:59:59'), 'America/New_York')),
                array(array(strtotime('2010-10-09 16:00:00'), 'GMT'), array(strtotime('2010-10-09 12:00:00'), 'America/New_York')),
            )
        );
    }
}
