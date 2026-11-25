<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;

final class BoundDocumentColorTest extends TestCase
{
    public function testCoreResolvesLegacyPaletteFunctionsInUnifiedComputedData()
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><p id="p" style="color:color-mix(in srgb, red 50%, blue)">Text</p>');
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        $widget = $view->getById('p');
        $ids = new \ReflectionProperty($sheet, 'widgetDeclarations')->getValue($sheet);
        $values = new \ReflectionProperty($sheet, 'declarations')->getValue($sheet);
        self::assertSame('#670077', $values[$ids[$widget]]['color']);
        self::assertSame('#670077', $sheet->getStyles($widget)[0]->getColor()->toHex());
        self::assertSame('color-mix(in srgb, red 50%, blue)', $sheet->getComputedValue($widget, 'color'));
    }

    public function testColorAndBackgroundSemanticsPreserveTheOriginalTerminalValues()
    {
        foreach ([
            'color:red;background:blue' => ['#cd0000', '#0000ee', null, true, true],
            'color:208;background:bright-red' => ['#ff8700', '#ff0000', null, false, true],
            'opacity:.5;color:light-dark(red,blue)' => ['#0000ee', null, null, true, null],
            '--x:default;color:var(--x)' => ['#e5e5e5', null, null, true, null],
            'accent-color:orange;color:AccentColorText;background:AccentColor' => ['#000000', '#ffa500', null, false, false],
            'color:color-mix(in srgb,currentColor 50%,blue)' => [null, null, null, null, null],
            'background-color:color-mix(in srgb,currentColor 50%,blue)' => ['#00cd00', '#006777', null, true, false],
            'color:light-dark(red,blue)' => ['#0000ee', null, null, true, null],
            'color:currentColor;background:currentColor' => [null, null, null, null, null],
            'color:rgba(255,0,0,.5);background:rgba(0,0,255,.5)' => ['#b93a79', '#7373f2', ['#0000ff', 50], false, false],
            'color:red;all:initial' => ['#e5e5e5', null, null, true, null],
            'background:red;background:none' => ['#00cd00', null, null, true, null],
            'background:blue;background:0 0' => ['#00cd00', null, null, true, null],
            'background:blue;background:linear-gradient(red,blue) red;background-color:green' => ['#00cd00', '#00cd00', null, true, true],
        ] as $declarations => $expected) {
            $view = new HtmlBuilder()->build('<!DOCTYPE html><section style="color:green;background:white"><p id="p" style="'.$declarations.'">Text</p></section>');
            $sheet = $view->getStyleSheet();
            $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
            $widget = $view->getById('p');
            $style = $sheet->getStyles($widget)[0] ?? null;
            self::assertSame($expected, [$style?->getColor()?->toHex(), $style?->getBackground()?->toHex(), null !== ($alpha = $style?->getTranslucentBackground()) ? [$alpha[0]->toHex(), $alpha[1]] : null, $style?->getColor()?->isThemed(), $style?->getBackground()?->isThemed()], $declarations);
        }
    }
}
