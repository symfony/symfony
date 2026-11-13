<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Css\Animation;
use Symfony\Component\Tui\Css\DeclarationMapper;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Style\MediaContext;

final class ElementFunctionsTest extends TestCase
{
    public function testSiblingIndexAndCount()
    {
        $view = $this->build('li { --i: sibling-index(); --n: sibling-count(); order: calc(sibling-count() - sibling-index()) }', '<ul><li id="a">a</li><li id="b">b</li><li id="c">c</li></ul>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);

        $this->assertSame('1', $value('a', '--i'));
        $this->assertSame('3', $value('c', '--i'));
        $this->assertSame('3', $value('b', '--n'));
        $this->assertSame('calc(3 - 2)', $value('b', 'order'));
    }

    public function testSiblingIndexIsInheritedAsTheValueOfTheParent()
    {
        $view = $this->build('li { --i: sibling-index() }', '<ul><li>a</li><li><p id="p">b</p><p id="q">c</p></li></ul>');

        $this->assertSame('2', $view->getStyleSheet()->getComputedValue($view->getById('q'), '--i'));
    }

    public function testElementsMatchingTheSameRulesDoNotShareTheirValues()
    {
        $view = $this->build('p { color: attr(data-color type(<color>), gray) }', '<div><p id="a" data-color="red">a</p><p id="b" data-color="blue">b</p><p id="c" data-color="nope">c</p><p id="d">d</p></div>');
        $value = static fn (string $id) => $view->getStyleSheet()->getComputedValue($view->getById($id), 'color');

        $this->assertSame('red', $value('a'));
        $this->assertSame('blue', $value('b'));
        $this->assertSame('gray', $value('c'), 'Not of the type: the fallback.');
        $this->assertSame('gray', $value('d'), 'Missing: the fallback.');
    }

    public function testAttrTypes()
    {
        $view = $this->build(<<<'CSS'
            #p {
                --string: attr(title);
                --raw: attr(title raw-string);
                --number: attr(data-n number);
                --unit: attr(data-n ch);
                --percent: attr(data-n %);
                --list: attr(data-sizes type(<length>+));
                --nested: attr(data-missing, attr(data-n number));
            }
            CSS, '<p id="p" title=\'say "hi"\' data-n="12" data-sizes="1ch 2ch">a</p>');
        $value = static fn (string $property) => $view->getStyleSheet()->getComputedValue($view->getById('p'), $property);

        $this->assertSame('"say \"hi\""', $value('--string'));
        $this->assertSame('"say \"hi\""', $value('--raw'));
        $this->assertSame('12', $value('--number'));
        $this->assertSame('12ch', $value('--unit'));
        $this->assertSame('12%', $value('--percent'));
        $this->assertSame('1ch 2ch', $value('--list'));
        $this->assertSame('12', $value('--nested'), 'A fallback takes attr() too.');
    }

    public function testAnAttrWithoutValueNorFallbackIsInvalidAtComputedValueTime()
    {
        $view = $this->build('div { color: green } p { color: attr(data-color type(<color>)); --n: attr(data-n number) }', '<div><p id="p" data-n="x">a</p></div>');
        $sheet = $view->getStyleSheet();

        $this->assertSame('green', $sheet->getComputedValue($view->getById('p'), 'color'), 'Invalid: it inherits, like unset.');
        $this->assertNull($sheet->getComputedValue($view->getById('p'), '--n'));
    }

    public function testAttrInAStringIsText()
    {
        $view = $this->build('p { --v: "attr(data-v)" attr(data-v) }', '<p id="p" data-v="x">a</p>');

        $this->assertSame('"attr(data-v)" "x"', $view->getStyleSheet()->getComputedValue($view->getById('p'), '--v'));
    }

    public function testAnAttributeValueIsNotSubstitutedAgain()
    {
        $view = $this->build('p { --v: attr(data-v) }', '<p id="p" data-v="attr(data-v)">a</p>');

        $this->assertSame('"attr(data-v)"', $view->getStyleSheet()->getComputedValue($view->getById('p'), '--v'));
    }

    public function testAttrOnInlineElementsAndContentKeepsItsOwn()
    {
        $view = $this->build('a { --href: attr(href) } a::after { content: " (" attr(href) ")" }', '<p id="p">see <a href="x.html">this</a></p>');

        $this->assertSame('see this (x.html)', AnsiUtils::stripAnsiCodes($view->getById('p')->getText()));
    }

    public function testIntegersAndTimesTakeMathFunctions()
    {
        $this->assertEqualsWithDelta(0.3, Animation::time('calc(3 * 100ms)'), 1e-9);
        $this->assertEqualsWithDelta(1.5, Animation::time('calc(1s + 500ms)'), 1e-9);
        $this->assertEqualsWithDelta(0.2, Animation::time('min(200ms, 1s)'), 1e-9);
        $this->assertNull(Animation::time('calc(3 * 100px)'));

        $view = $this->build('.bar { display: flex } span { order: calc(sibling-count() - sibling-index()); animation-delay: calc(sibling-index() * 100ms) }', '<div class="bar"><span id="a">a</span><span id="b">b</span></div>');
        $this->assertSame('calc(2 - 1)', $view->getStyleSheet()->getComputedValue($view->getById('a'), 'order'));
        $this->assertEqualsWithDelta(0.2, Animation::time($view->getStyleSheet()->getComputedValue($view->getById('b'), 'animation-delay') ?? ''), 1e-9);
    }

    public function testOrderTakesMathFunctions()
    {
        $mapper = new DeclarationMapper();

        $this->assertSame(2, $mapper->map(['order' => 'calc(5 - 3)'], new MediaContext())->getOrder());
        $this->assertSame(-1, $mapper->map(['order' => 'round(-0.6)'], new MediaContext())->getOrder());
        $this->assertNull($mapper->map(['order' => 'calc(1ch)'], new MediaContext())->getOrder());
    }

    private function build(string $css, string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
    }
}
