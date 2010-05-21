<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder\Comparator;

use Symfony\Components\Finder\Comparator\Comparator;

class ComparatorTest extends \PHPUnit_Framework_TestCase
{
    public function testSetOperator()
    {
        $comparator = new Comparator();
        try {
            $comparator->setOperator('foo');
            $this->fail('->setOperator() throws an \InvalidArgumentException if the operator is not valid.');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->setOperator() throws an \InvalidArgumentException if the operator is not valid.');
        }
    }

    /**
     * @dataProvider getTestData
     */
    public function testTest($operator, $target, $match, $noMatch)
    {
        $c = new Comparator();
        $c->setOperator($operator);
        $c->setTarget($target);

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
            array('<', '1000', array('500', '999'), array('1000', '1500')),
        );
    }
}
