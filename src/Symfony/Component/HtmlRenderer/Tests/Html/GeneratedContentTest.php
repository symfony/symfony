<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class GeneratedContentTest extends TestCase
{
    public function testControlValueFlowsBetweenPseudoElements()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>input::before{content:"["}input::after{content:"]"}</style><input value="hello">', new RenderingContext(800, 640));
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        self::assertSame(['[', 'hello', ']'], $texts);
    }

    public function testInteractiveFixturesRenderWithNativeSelectors()
    {
        foreach (['css-selectors-forms', 'forms', 'html-interactive', 'html-summary'] as $fixture) {
            $html = file_get_contents(__DIR__.'/../Fixtures/Compat/'.$fixture.'.html');
            $result = new HtmlRenderer()->render($html, new RenderingContext(800, 640));
            self::assertNotEmpty($result->getDisplayList()->getOperations(), $fixture);
        }
    }

    public function testCountersFollowBeforeChildrenAfterDocumentOrder()
    {
        $result = new HtmlRenderer()->render('<style>main { counter-reset: section } main::before { counter-increment: section; content: counter(section) ":" } span { counter-increment: section } span::before { content: counter(section) ":" } main::after { content: ":" counter(section) }</style><main>A<span>B</span>C</main>', new RenderingContext(500, 200));
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        self::assertSame(['1:', 'A', '2:', 'B', 'C', ':2'], $texts);
    }

    public function testOrderedListAttributesAndCustomCounterStylesProduceMarkerBoxes()
    {
        $result = new HtmlRenderer()->render('<style>@counter-style stars { system: cyclic; symbols: "*"; suffix: " " } ul { list-style: stars inside }</style><ol start="5" reversed><li>A</li><li value="9">B</li><li>C</li></ol><ul><li>D</li></ul>', new RenderingContext(500, 200));
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        foreach (['5. ', '9. ', '8. ', '* '] as $marker) {
            self::assertContains($marker, $texts);
        }
    }

    public function testNestedLanguageQuotesAndHiddenCountersRemainDocumentState()
    {
        $result = new HtmlRenderer()->render('<style>q::before { content: open-quote } q::after { content: close-quote } main { counter-reset: item } p { counter-increment: item } p::before { content: counter(item) }</style><main lang="fr"><q>A<q>B</q>C</q><p hidden>Hidden</p><p>D</p></main>', new RenderingContext(500, 200));
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        self::assertSame(['«', 'A', '“', 'B', '”', 'C', '»', '1', 'D'], $texts);
    }
}
