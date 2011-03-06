<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Finder\Comparator;

use Symfony\Component\Finder\Comparator\NumberComparator;

class NumberComparatorTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        try {
            new NumberComparator('foobar');
            $this->fail('__construct() throws an \InvalidArgumentException if the test expression is not valid.');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '__construct() throws an \InvalidArgumentException if the test expression is not valid.');
        }
    }

    /**
     * @dataProvider getTestData
     */
    public function testTest($test, $match, $noMatch)
    {
        $c = new NumberComparator($test);

        foreach ($match as $m) {
            $this->assertTrue($c->test($m), '->test() tests a string against the expression');
        }

        foreach ($noMatch as $m) {
            $this->assertFalse($c->test($m), '->test() tests a string against the expression');
        }
    }

    public function getTestData()
    {
        return array(
            array('< 1000', array('500', '999'), array('1000', '1500')),

            array('< 1K', array('500', '999'), array('1000', '1500')),
            array('<1k', array('500', '999'), array('1000', '1500')),
            array('  < 1 K ', array('500', '999'), array('1000', '1500')),
            array('<= 1K', array('1000'), array('1001')),
            array('> 1K', array('1001'), array('1000')),
            array('>= 1K', array('1000'), array('999')),

            array('< 1KI', array('500', '1023'), array('1024', '1500')),
            array('<= 1KI', array('1024'), array('1025')),
            array('> 1KI', array('1025'), array('1024')),
            array('>= 1KI', array('1024'), array('1023')),

            array('1KI', array('1024'), array('1023', '1025')),
            array('==1KI', array('1024'), array('1023', '1025')),

            array('==1m', array('1000000'), array('999999', '1000001')),
            array('==1mi', array(1024*1024), array(1024*1024-1, 1024*1024+1)),

            array('==1g', array('1000000000'), array('999999999', '1000000001')),
            array('==1gi', array(1024*1024*1024), array(1024*1024*1024-1, 1024*1024*1024+1)),
        );
    }
}
