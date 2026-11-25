<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeMarkerColorTest extends TestCase
{
    public function testGroupColorIsOnlyANativeFallbackForMarkersWithoutTheirOwnColor(): void
    {
        $sheet = new CssStyleSheet();
        $sheet->setAnimationHandlers(static function (): void {}, static fn (): float => .5);
        $view = new HtmlBuilder($sheet)->useHtmlRenderer()->build('<style>#s{height:8;overflow:auto;scroll-marker-group:after}#s::scroll-marker-group{color:gray}p{color:red;margin:0}p::scroll-marker{content:attr(data-label)}#own::scroll-marker{color:cyan}#inherit::scroll-marker{color:inherit}#unset::scroll-marker{color:unset}#animated::scroll-marker{animation:pulse 1s linear infinite}@keyframes pulse{from{color:blue}to{color:green}}</style><div id="s"><p data-label="One">first</p><p id="own" data-label="Two">second</p><p id="inherit" data-label="Three">third</p><p id="unset" data-label="Four">fourth</p><p id="animated" data-label="Five">fifth</p></div>');
        $root = new ContainerWidget();
        $root->add($view->getRoot());
        new FacadeRenderer(new Renderer())->renderFrame($root, 60, 20);
        $colors = [];
        foreach ($view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations() as $operation) {
            if ('text' === $operation['type']) {
                $colors[$operation['text']] = Value::color($operation['color'], new MediaContext())->toHex();
            }
        }
        self::assertSame(Value::color('gray', new MediaContext())->toHex(), $colors['One']);
        self::assertSame(Value::color('cyan', new MediaContext())->toHex(), $colors['Two']);
        self::assertSame(Value::color('red', new MediaContext())->toHex(), $colors['Three']);
        self::assertSame(Value::color('red', new MediaContext())->toHex(), $colors['Four']);
        self::assertNotSame($colors['One'], $colors['Five']);
        self::assertSame(Value::color('blue', new MediaContext())->toHex(), $colors['Five']);
    }
}
