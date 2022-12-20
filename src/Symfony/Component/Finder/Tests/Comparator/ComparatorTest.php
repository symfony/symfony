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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Finder\Comparator\Comparator;

class ComparatorTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testGetSetOperator()
    {
        $comparator = new Comparator('some target');

        $this->expectDeprecation('Since symfony/finder 5.4: "Symfony\Component\Finder\Comparator\Comparator::setOperator" is deprecated. Set the operator via the constructor instead.');
        $comparator->setOperator('>');
        self::assertEquals('>', $comparator->getOperator(), '->getOperator() returns the current operator');
    }

    public function testInvalidOperator()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid operator "foo".');

        new Comparator('some target', 'foo');
    }

    /**
     * @group legacy
     */
    public function testGetSetTarget()
    {
        $this->expectDeprecation('Since symfony/finder 5.4: Constructing a "Symfony\Component\Finder\Comparator\Comparator" without setting "$target" is deprecated.');
        $comparator = new Comparator();

        $this->expectDeprecation('Since symfony/finder 5.4: "Symfony\Component\Finder\Comparator\Comparator::setTarget" is deprecated. Set the target via the constructor instead.');
        $comparator->setTarget(8);
        self::assertEquals(8, $comparator->getTarget(), '->getTarget() returns the target');
    }

    /**
     * @dataProvider provideMatches
     */
    public function testTestSucceeds(string $operator, string $target, string $testedValue)
    {
        $c = new Comparator($target, $operator);

        self::assertSame($target, $c->getTarget());
        self::assertSame($operator, $c->getOperator());

        self::assertTrue($c->test($testedValue));
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
        $c = new Comparator($target, $operator);

        self::assertFalse($c->test($testedValue));
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
