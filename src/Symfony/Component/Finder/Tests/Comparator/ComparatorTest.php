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
    public function testInvalidOperator()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator "foo".');

        new Comparator('some target', 'foo');
    }

    /**
     * @dataProvider provideMatches
     */
    public function testTestSucceeds(string $operator, string $target, string $testedValue)
    {
        $c = new Comparator($target, $operator);

        $this->assertSame($target, $c->getTarget());
        $this->assertSame($operator, $c->getOperator());

        $this->assertTrue($c->test($testedValue));
    }

    public static function provideMatches(): array
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

        $this->assertFalse($c->test($testedValue));
    }

    public static function provideNonMatches(): array
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
