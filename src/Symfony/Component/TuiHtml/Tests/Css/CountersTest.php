<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

final class CountersTest extends TestCase
{
    public function testSiblingsShareTheCounterAndNestedResetsNest()
    {
        $this->assertSame(['1 a', '2 b', '2.1 c', '2.2 d', '3 e'], $this->texts('
            <style>
              ol { counter-reset: n; list-style: none }
              li { counter-increment: n }
              li::before { content: counters(n, ".") " " }
            </style>
            <ol><li>a</li><li><p>b</p><ol><li>c</li><li>d</li></ol></li><li>e</li></ol>'));
    }

    public function testAResetBySiblingsReplacesTheCounter()
    {
        $this->assertSame(['x', '1.1 a', '1.2 b', 'y', '2.1 c'], $this->texts('
            <style>
              h2 { counter-increment: h; counter-reset: s }
              p { counter-increment: s }
              p::before { content: counter(h) "." counter(s) " " }
            </style>
            <main><h2>x</h2><p>a</p><p>b</p><h2>y</h2><p>c</p></main>'));
    }

    public function testAnElementNotDisplayedDoesNotCount()
    {
        $this->assertSame(['x', '0.1 a', '0.2 b'], \array_slice($this->texts('
            <style>
              h2 { counter-increment: h; counter-reset: s }
              .gone { display: none }
              p { counter-increment: s }
              p::before { content: counter(h) "." counter(s) " " }
            </style>
            <main><h2 class="gone">x</h2><p>a</p><p>b</p></main>'), 0, 3));
    }

    public function testACounterOutOfScopeIsZero()
    {
        $this->assertSame(['0 a'], $this->texts('<style>p::before { content: counter(missing) " " }</style><p>a</p>'));
    }

    public function testHugeValuesAreClampedAndStylesFallBackToDecimal()
    {
        $this->assertSame(['2147483647 a', '-5 b', 'MMMCMXCIX c'], $this->texts('
            <style>
              .big { counter-set: n 99999999999999999999 }
              .big::before { content: counter(n, upper-roman) " " }
              .neg { counter-set: n -5 }
              .neg::before { content: counter(n, lower-alpha) " " }
              .max { counter-set: n 3999 }
              .max::before { content: counter(n, upper-roman) " " }
            </style>
            <main style="counter-reset: n"><p class="big">a</p><p class="neg">b</p><p class="max">c</p></main>'));
    }

    public function testListMarkersAreBounded()
    {
        $this->assertSame(['2147483647. a', '2147483647. b'], $this->texts('<ol start="99999999999999999999" style="list-style-type: upper-roman"><li>a</li><li>b</li></ol>'));
    }

    public function testQuotesNestDeeperThanTheirPairs()
    {
        $this->assertSame(['"a [b [c] b] a" end'], $this->texts('<style>p { quotes: \'"\' \'"\' "[" "]" }</style><p><q>a <q>b <q>c</q> b</q> a</q> end</p>'), 'The last pair goes on.');
    }

    public function testACloseQuoteWithoutAnOpenOneDrawsNothing()
    {
        $this->assertSame(['x'], $this->texts('<style>p::after { content: close-quote }</style><p>x</p>'));
    }

    public function testInvalidQuotesKeepTheDefault()
    {
        $this->assertSame(['“a”'], $this->texts('<style>q { quotes: "only one" }</style><p><q>a</q></p>'));
    }

    private function texts(string $html): array
    {
        $view = (new HtmlBuilder())->build($html);
        $view->computeStyles();
        $texts = [];
        $walk = static function ($widget) use (&$walk, &$texts): void {
            if ($widget instanceof RichTextWidget && '' !== trim($widget->getPlainText())) {
                $texts[] = trim($widget->getMarker().$widget->getPlainText());
            }
            if ($widget instanceof \Symfony\Component\Tui\Widget\ParentInterface) {
                foreach ($widget->all() as $child) {
                    $walk($child);
                }
            }
        };
        $walk($view->getRoot());

        return $texts;
    }
}
