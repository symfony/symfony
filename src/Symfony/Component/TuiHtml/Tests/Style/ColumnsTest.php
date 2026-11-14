<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Css\DeclarationMapper;
use Symfony\Component\TuiHtml\Style\Columns;
use Symfony\Component\TuiHtml\Style\MediaContext;

final class ColumnsTest extends TestCase
{
    public function testResolve()
    {
        $this->assertSame([3, 18], (new Columns(3))->resolve(58), 'The room shared, gaps of two.');
        $this->assertSame([4, 13], (new Columns(null, 12))->resolve(58), 'As many as fit at the least width.');
        $this->assertSame([2, 28], (new Columns(2, 12))->resolve(58), 'At most the count.');
        $this->assertSame([1, 10], (new Columns(null, 20))->resolve(10), 'At least one.');
        $this->assertSame([5, 1], (new Columns(50, null, 1))->resolve(9), 'A column is a cell at least.');
    }

    public function testCss()
    {
        $mapper = new DeclarationMapper();
        $media = new MediaContext();
        $this->assertNull($mapper->map(['columns' => '1'], $media)->getColumns(), 'One column is no multi-column layout.');
        $columns = $mapper->map(['columns' => '12ch 3', 'column-gap' => '1', 'column-rule' => 'dotted red'], $media)->getColumns();
        $this->assertSame([3, 12, 1, '┊'], [$columns?->count, $columns?->width, $columns?->gap, $columns?->rule]);
        $this->assertNotNull($columns?->color);
        $this->assertSame('#123456', $mapper->map(['column-count' => '2', 'column-rule-color' => '#123456'], $media)->getColumns()?->color?->toHex());
        $this->assertNull($mapper->map(['column-count' => '2', 'column-rule' => '0 solid'], $media)->getColumns()?->rule, 'A zero width rule is none.');
        $this->assertSame(2, $mapper->map(['column-count' => '2'], $media)->getColumns()?->gap, 'The gap is 1em.');
        $this->assertTrue($mapper->map(['break-inside' => 'avoid'], $media)->getAvoidBreak());
    }
}
