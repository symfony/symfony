<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ColumnLayoutTest extends TestCase
{
    private function render(string $content, string $css = '')
    {
        return (new HtmlRenderer())->render('<style>main{width:100.5px;columns:2;column-gap:.5px;font-size:10px;line-height:10px}p,h2{margin:0;font-size:10px;line-height:10px}'.$css.'</style><main>'.$content.'</main>', new RenderingContext(200, 200));
    }

    public function testBalancedColumnsRetainFractionalWidthsAndDocumentOrder()
    {
        $result = $this->render('<p>a</p><p>b</p><p>c</p><p>d</p>');
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertSame(50.0, $result->getBounds($elements[0])[0]->width);
        self::assertSame(0.0, $result->getBounds($elements[1])[0]->x);
        self::assertSame(50.5, $result->getBounds($elements[2])[0]->x);
        self::assertSame(0.0, $result->getBounds($elements[2])[0]->y);
        self::assertSame($elements[2], $result->hitTest(51, 1));
        self::assertSame('abcd', implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text')));
    }

    public function testAutoFillUsesDefiniteHeightAndForcedBreaksStartNewColumns()
    {
        $result = $this->render('<p>a</p><p>b</p><p>c</p><p>d</p>', 'main{height:30.25px;column-fill:auto}');
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertSame(0.0, $result->getBounds($elements[2])[0]->x);
        self::assertSame(50.5, $result->getBounds($elements[3])[0]->x);
        $result = $this->render('<p>a</p><p style="break-before:column">b</p><p>c</p>');
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertSame(50.5, $result->getBounds($elements[1])[0]->x);
        self::assertSame(10.0, $result->getBounds($elements[2])[0]->y);
    }

    public function testAvoidKeepsBlockTogetherAndWidowsConstrainTextFragments()
    {
        $result = $this->render('<section style="break-inside:avoid"><p>a</p><p>b</p><p>c</p></section><p>d</p>');
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertSame(0.0, $result->getBounds($elements[2])[0]->x);
        self::assertSame(50.5, $result->getBounds($elements[3])[0]->x);
        $result = $this->render('<p id="text">1<br>2<br>3<br>4</p>', 'p{orphans:1;widows:3}');
        $bounds = $result->getBounds($result->getDocument()->querySelector('#text'));
        self::assertCount(2, $bounds);
        self::assertSame(10.0, $bounds[0]->height);
        self::assertSame(30.0, $bounds[1]->height);
    }

    public function testNestedSpannerSeparatesBalancedRunsWithoutLosingContent()
    {
        $result = $this->render('<section><p>a</p><p>b</p><h2>Title</h2><p>c</p><p>d</p></section>', 'h2{column-span:all}');
        $heading = $result->getBounds($result->getDocument()->querySelector('h2'))[0];
        self::assertSame(100.5, $heading->width);
        self::assertSame(10.0, $heading->y);
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertSame(20.0, $result->getBounds($elements[2])[0]->y);
        self::assertSame(50.5, $result->getBounds($elements[3])[0]->x);
        self::assertSame('abTitlecd', implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text')));
    }

    public function testCjkTextIsFragmentedAtItsMeasuredLines()
    {
        $result = $this->render('<p>日本語中文日本語中文日本語中文日本語中文</p>', 'main{width:40px;column-gap:0}p{orphans:1;widows:1}');
        $bounds = $result->getBounds($result->getDocument()->querySelector('p'));
        self::assertCount(2, $bounds);
        self::assertSame(20.0, $bounds[1]->x);
        self::assertSame('日本語中文日本語中文日本語中文日本語中文', implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text')));
    }

    public function testRtlColumnOrderAndRulesOnlyBetweenOccupiedColumns()
    {
        $result = $this->render('<p>a</p><p>b</p><p>c</p><p>d</p>', 'main{columns:3;column-gap:2px;direction:rtl;height:30px;column-fill:auto;column-rule:1px dashed red}');
        $elements = $result->getDocument()->querySelectorAll('p');
        self::assertGreaterThan($result->getBounds($elements[3])[0]->x, $result->getBounds($elements[0])[0]->x);
        $rules = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
        self::assertCount(1, $rules);
        self::assertSame('dashed', $rules[0]['sides']['left']['style']);
        self::assertSame(30.0, $rules[0]['height']);
    }

    public function testDefiniteHeightCreatesVisibleOverflowColumnsWithoutLosingText()
    {
        $result = $this->render('<p>a</p><p>b</p><p>c</p><p>d</p><p>e</p>', 'main{height:10px;column-fill:auto;overflow:visible}');
        $elements = $result->getDocument()->querySelectorAll('p');
        $last = $result->getBounds($elements[4])[0];
        self::assertSame(202.0, $last->x);
        self::assertSame(0.0, $last->y);
        self::assertSame($elements[4], $result->hitTest(203, 1));
        self::assertSame('abcde', implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text')));
    }
}
