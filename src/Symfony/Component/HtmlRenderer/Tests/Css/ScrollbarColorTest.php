<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ScrollbarColorTest extends TestCase
{
    public function testCompoundColorsNormalizeAndCurrentColorInheritsAsKeyword()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>section{color:red;scrollbar-color:currentColor blue;scrollbar-width:thin}p{color:green}</style><section><p>Child</p></section>', new RenderingContext(400, 200));
        $parent = $result->getComputedStyle($result->getDocument()->querySelector('section'));
        $child = $result->getComputedStyle($result->getDocument()->querySelector('p'));
        self::assertSame('currentcolor #0000ff', $parent['scrollbar-color']);
        self::assertSame($parent['scrollbar-color'], $child['scrollbar-color']);
        self::assertSame('green', $child['color']);
        self::assertArrayNotHasKey('scrollbar-width', $child);
    }

    public function testFunctionsSystemColorsAliasesAndAutoUseExplicitContext()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>#a{scrollbar-color:brand light-dark(red,CanvasText)}#b{scrollbar-color:rgba(1,2,3,.5) currentColor}#c{scrollbar-color:auto}</style><p id="a">A</p><p id="b">B</p><p id="c">C</p>');
        $resolver = new \Symfony\Component\HtmlRenderer\Css\StyleResolver(new \Symfony\Component\HtmlRenderer\Resource\ResourceLoader(), new FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), ['prefers-color-scheme' => 'dark'], '', ['brand' => '#123456']);
        $styles = $resolver->resolve($document, 400, 200);
        self::assertSame('#123456 #ffffff', $styles[$document->getElementById('a')]['scrollbar-color']);
        self::assertSame('rgba(1, 2, 3, 0.5) currentcolor', $styles[$document->getElementById('b')]['scrollbar-color']);
        self::assertSame('auto', $styles[$document->getElementById('c')]['scrollbar-color']);
    }
}
