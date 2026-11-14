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
use Symfony\Component\TuiHtml\Style\GridPlacement;
use Symfony\Component\TuiHtml\Style\GridTemplate;
use Symfony\Component\TuiHtml\Style\MediaContext;

final class GridTemplateTest extends TestCase
{
    public function testFractionsShareTheRoomLeft()
    {
        $template = new GridTemplate([['cells', 10, 0], ['fr', 1, 0], ['fr', 2, 0]]);
        $this->assertSame([10, 16, 32], $template->resolve(60, 1, static fn () => 0));
    }

    public function testAutoTracksTakeTheirContent()
    {
        $template = new GridTemplate([['auto', 0, 0], ['fr', 1, 0]]);
        $this->assertSame([7, 51], $template->resolve(60, 2, static fn (int $index) => 0 === $index ? 7 : 0));
    }

    public function testOnlyAutoTracksStretchAndEmptyOnesTakeNoColumn()
    {
        $style = (new DeclarationMapper())->map(['display' => 'grid', 'grid-template-columns' => 'min-content minmax(0, auto) min-content'], new MediaContext());
        $content = static fn (int $index) => 1 === $index ? 9 : 0;
        $this->assertSame([0, 30, 0], $style->getGridTemplate()?->resolve(30, 0, $content), 'Like the label of a button of GitHub between its icons: the min-content tracks do not stretch, the empty ones take nothing.');
        $this->assertSame([0, 9, 0], $style->getGridTemplate()->resolve(30, 0, $content, true), 'As wide as the label, sized by its content.');
        $this->assertSame([10, 10, 10], (new GridTemplate([['auto', 0, 0], ['auto', 0, 0], ['auto', 0, 0]]))->resolve(30, 0, static fn () => 0), 'Auto ones share the room.');
    }

    public function testAutoFillFitsAsManyTracksAsTheirMinimumAllows()
    {
        $template = new GridTemplate([], ['fr', 1, 18]);
        $this->assertSame(3, $template->count(60, 1));
        $this->assertSame(1, $template->count(30, 1));
        $this->assertSame(1, $template->count(5, 1), 'Always one.');
        $this->assertSame([20, 19, 19], $template->resolve(60, 1, static fn () => 0));
    }

    public function testCss()
    {
        $mapper = new DeclarationMapper();
        $media = new MediaContext();
        $style = $mapper->map(['display' => 'grid', 'grid-template-columns' => 'repeat(3, 1fr)', 'gap' => '2 1ch'], $media);
        $this->assertSame(3, $style->getGridTemplate()?->count(60, 1));
        $this->assertSame(1, $style->getGap());
        $this->assertSame(2, $style->getRowGap());
        $this->assertNull($mapper->map(['grid-template-columns' => '1fr 1fr'], $media)->getGridTemplate(), 'Only on a grid.');
        $this->assertSame(1, $mapper->map(['display' => 'grid'], $media)->getGridTemplate()?->count(60, 0), 'One column without a template.');
        $this->assertEquals(new GridPlacement(null, null, null, 2), $mapper->map(['grid-column' => 'span 2'], $media)->getGridPlacement());
        $this->assertEquals(new GridPlacement(null, 1, -1), $mapper->map(['grid-column' => '1 / -1'], $media)->getGridPlacement());
        $this->assertEquals(new GridPlacement(null, 2, 4, 1, 1, null, 3), $mapper->map(['grid-column' => '2 / 4', 'grid-row' => '1 / span 3'], $media)->getGridPlacement());
        $this->assertEquals(new GridPlacement(null, 3, null, 1, 2, 4), $mapper->map(['grid-area' => '2 / 3 / 4'], $media)->getGridPlacement());
        $this->assertEquals(new GridPlacement('side'), $mapper->map(['grid-area' => 'side'], $media)->getGridPlacement());
        $this->assertEquals(new GridPlacement(null, 2, null, 2), $mapper->map(['grid-column' => 'span 2', 'grid-column-start' => '2'], $media)->getGridPlacement(), 'The longhand wins.');
        $this->assertFalse($mapper->supports('grid-row', 'span 0'));
        $style = $mapper->map(['display' => 'grid', 'grid-template-areas' => '"head head" "side main"', 'grid-template-rows' => 'auto 5'], $media);
        $this->assertSame(['head' => [0, 0, 0, 1], 'side' => [1, 0, 1, 0], 'main' => [1, 1, 1, 1]], $style->getGridTemplate()?->areas);
        $this->assertSame([null, 5], $style->getGridTemplate()->rows);
        $this->assertSame(2, $style->getGridTemplate()->count(60, 0), 'The areas make the columns.');
        $this->assertFalse($mapper->supports('grid-template-areas', '"a b" "b a"'), 'Areas are rectangles.');
        $this->assertFalse($mapper->supports('grid-template-areas', '"a b" "c"'), 'Rows have the same columns.');
        $this->assertFalse($mapper->supports('grid-template-columns', 'repeat(auto-fill, 1fr 2fr)'));
        $this->assertTrue($mapper->supports('grid-template-columns', 'minmax(10ch, 1fr) auto 25%'));
    }

    public function testImplicitTracksTakeTheAutoValuesInTurn()
    {
        $template = new GridTemplate([], null, [], [], 1, false, [], [], false, ['cells', 6, 0], [], [1, 4], [['cells', 6, 0], ['cells', 12, 0]]);
        $this->assertSame([1, 4, 1, 4], array_map($template->autoRow(...), [0, 1, 2, 3]));
        $this->assertSame([6, 12, 6], array_map(static fn (int $i) => (int) $template->autoColumn($i)[1], [0, 1, 2]));
        // One value: every implicit track
        $single = new GridTemplate([], null, [], [], 2, false, [], [], false, ['cells', 3, 0]);
        $this->assertSame([2, 2], [$single->autoRow(0), $single->autoRow(5)]);
        $this->assertSame(['cells', 3, 0], $single->autoColumn(7));
        $this->assertNull((new GridTemplate([]))->autoRow(0));
    }

    public function testNamedLinesOfARepeatThatFillsTheRoom()
    {
        // [edge] 4ch repeat(auto-fill, [cell] 8ch) [tail] 4ch
        $template = new GridTemplate([['cells', 4, 0], ['cells', 4, 0]], ['cells', 8, 0], [], [], null, false, ['edge' => [1], 'tail' => [3]], [], false, null, [], [], [], 1, ['cell' => [0]]);
        $this->assertSame(['edge' => [1], 'tail' => [7], 'cell' => [2, 3, 4, 5, 6]], $template->lines(true, 7));
        $this->assertSame(7, $template->line('tail', true, true, 7));
        $this->assertSame(3, $template->line('cell 2', true, true, 7));
        $this->assertSame(6, $template->line('cell -1', true, true, 7));
        $this->assertNull($template->line('cell 9', true, true, 7));
        $this->assertNull($template->line('cell 0', true, true, 7));
        // The repeated tracks stay where the repeat is, not at the end
        $this->assertSame([4, 8, 8, 8, 8, 8, 4], $template->resolve(60, 1, static fn () => 0));
    }
}
