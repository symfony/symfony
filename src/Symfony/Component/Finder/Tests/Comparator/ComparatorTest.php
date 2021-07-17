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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Comparator\Comparator;

class ComparatorTest extends TestCase
{
    public function testGetSetOperator()
    {
        $comparator = new Comparator();
        $comparator->setOperator('>');
        $this->assertEquals('>', $comparator->getOperator(), '->getOperator() returns the current operator');
    }

    public function testInvalidOperator()
    {
        $comparator = new Comparator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator "foo".');
        $comparator->setOperator('foo');
    }

    public function testGetSetTarget()
    {
        $comparator = new Comparator();
        $comparator->setTarget(8);
        $this->assertEquals(8, $comparator->getTarget(), '->getTarget() returns the target');
    }

    /**
     * @dataProvider provideMatches
     */
    public function testTestSucceeds(string $operator, string $target, string $testedValue)
    {
        $c = new Comparator();
        $c->setOperator($operator);
        $c->setTarget($target);

        $this->assertTrue($c->test($testedValue));
    }

    public function provideMatches(): array
    {
        return [
            ['<', '1000', '500'],
            ['<', '1000', '999'],
            ['<=', '1000', '999'],
            ['!=', '1000', '999'],
            ['<=', '1000', '1000'],
            ['==', '1000', '1000'],
            ['>=', '1000', '1000'],
            ['>=', '1000', '1001'],
            ['>', '1000', '1001'],
            ['>', '1000', '5000'],
        ];
    }

    /**
     * @dataProvider provideNonMatches
     */
    public function testTestFails(string $operator, string $target, string $testedValue)
    {
        $c = new Comparator();
        $c->setOperator($operator);
        $c->setTarget($target);

        $this->assertFalse($c->test($testedValue));
    }

    public function provideNonMatches(): array
    {
        return [
            ['>', '1000', '500'],
            ['>=', '1000', '500'],
            ['>', '1000', '1000'],
            ['!=', '1000', '1000'],
            ['<', '1000', '1000'],
            ['<', '1000', '1500'],
            ['<=', '1000', '1500'],
        ];
    }
}
