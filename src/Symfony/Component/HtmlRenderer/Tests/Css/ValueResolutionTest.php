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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ValueResolutionTest extends TestCase
{
    public function testCustomFunctionsConditionsAndInvalidComputedValuesAreResolvedStandalone()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>@function --tone(--value <color>:red) returns <color>{result:var(--value)}@function --wrong(--value <color>) returns <color>{result:var(--value)}p{--variant:primary;color:--tone();background:if(style(--variant:primary):blue;else:green)}p.bad{color:red;color:--wrong(12px)}</style><p>Good</p><p class="bad">Bad</p>', new RenderingContext(400, 200));
        $document = $result->getDocument();
        self::assertSame('red', $result->getComputedStyle($document->querySelector('p'))['color']);
        self::assertSame('blue', $result->getComputedStyle($document->querySelector('p'))['background-color']);
        self::assertSame('#000000', $result->getComputedStyle($document->querySelector('.bad'))['color']);
    }

    public function testEnvironmentFallbackAndStandardColorFunctionRemainBackendIndependent()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p{color:env(not-a-variable,blue)}p.bad{color:red;color:env(not-a-variable)}p.mix{color:color-mix(in srgb,red 50%,blue)}</style><p>Fallback</p><p class="bad">Invalid</p><p class="mix">Mix</p>', new RenderingContext(400, 200));
        self::assertSame('blue', $result->getComputedStyle($result->getDocument()->querySelector('p'))['color']);
        self::assertSame('#000000', $result->getComputedStyle($result->getDocument()->querySelector('.bad'))['color']);
        self::assertSame('#800080', $result->getComputedStyle($result->getDocument()->querySelector('.mix'))['color']);
    }

    public function testDetailsContentInheritancePreservesNativeIdentityAndSummaryStyle()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>details::details-content{color:cyan}p.explicit{color:red}</style><details open><summary>Summary</summary><p>Child</p><p class="explicit">Override</p></details>');
        $source = $document->saveHtml();
        $child = $document->querySelector('p');
        $result = new HtmlRenderer()->render($document, new RenderingContext(400, 200));
        self::assertSame('cyan', $result->getComputedStyle($child)['color']);
        self::assertSame('red', $result->getComputedStyle($document->querySelector('.explicit'))['color']);
        self::assertSame('#000000', $result->getComputedStyle($document->querySelector('summary'))['color']);
        self::assertSame($child, $result->getDocument()->querySelector('p'));
        self::assertSame($source, $document->saveHtml());
    }
}
