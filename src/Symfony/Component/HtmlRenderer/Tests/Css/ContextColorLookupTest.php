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

final class ContextColorLookupTest extends TestCase
{
    public function testHostColorLookupIsExplicitAndDoesNotChangeStandaloneDefaults()
    {
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.0, 0.0, 238 / 255], 1.0] : null;
        $context = new RenderingContext(200, 100, null, null, null, null, [], '', [], null, null, $lookup);
        $html = '<p style="color:light-dark(blue,yellow)">Text</p>';
        $renderer = new HtmlRenderer();
        $result = $renderer->render($html, $context);
        self::assertSame('#0000ee', $result->getComputedStyle($result->getDocument()->querySelector('p'))['color']);
        self::assertSame($lookup, $context->withViewport(300, 200)->colorLookup);
        $default = $renderer->render($html, new RenderingContext(200, 100));
        self::assertSame('#0000ff', $default->getComputedStyle($default->getDocument()->querySelector('p'))['color']);
    }

    public function testChangingOnlyTheLookupRecomputesTheColor()
    {
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.0, 0.0, 238 / 255], 1.0] : null;
        $context = new RenderingContext(200, 100, null, null, null, null, [], '', [], null, null, $lookup);
        $session = (new HtmlRenderer())->createSession('<p style="color:light-dark(blue,yellow)">Text</p>', $context);
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('#0000ee', $session->render()->getComputedStyle($paragraph)['color']);
        $different = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[18 / 255, 0.0, 0.0], 1.0] : null;
        $session->setContext(new RenderingContext($context->width, $context->height, $context->textMeasurer, $context->resourceLoader, $context->layoutMetrics, $context->defaultFont, $context->mediaFeatures, $context->userAgentStylesheet, $context->colors, $context->hyphenator, $context->replacedContentResolver, $different));
        self::assertSame('#120000', $session->render()->getComputedStyle($paragraph)['color']);
    }

    public function testLookupAlsoResolvesNestedGradientOperandsAndBorderImages()
    {
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.0, 0.0, ('dark' === $scheme ? 238 : 204) / 255], 1.0] : null;
        $context = new RenderingContext(200, 100, null, null, null, null, ['prefers-color-scheme' => 'dark'], '', ['brand' => 'blue'], null, null, $lookup);
        $html = '<div style="width:50px;height:20px;background-image:linear-gradient(brand,color-mix(in srgb,blue 50%,black));border:1px solid;border-image-source:linear-gradient(blue,transparent)"></div>';
        $result = (new HtmlRenderer())->render($html, $context);
        $operations = $result->getDisplayList()->getOperations();
        $gradient = array_values(array_filter($operations, static fn (array $op): bool => 'gradient' === $op['type']))[0];
        $border = array_values(array_filter($operations, static fn (array $op): bool => 'border' === $op['type']))[0];
        self::assertSame([[0.0, 0.0, 238 / 255], 1.0], $gradient['gradient']['stops'][0][0]);
        self::assertSame([[0.0, 0.0, 119 / 255], 1.0], $gradient['gradient']['stops'][1][0]);
        self::assertSame([[0.0, 0.0, 238 / 255], 1.0], $border['source']['gradient']['stops'][0][0]);
        $default = (new HtmlRenderer())->render($html, new RenderingContext(200, 100, null, null, null, null, [], '', ['brand' => 'blue']));
        $gradient = array_values(array_filter($default->getDisplayList()->getOperations(), static fn (array $op): bool => 'gradient' === $op['type']))[0];
        self::assertSame([[0.0, 0.0, 1.0], 1.0], $gradient['gradient']['stops'][0][0]);
    }

    public function testColumnRuleUsesTheElementsColorSchemeWithTheSameLookup()
    {
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.0, 0.0, ('dark' === $scheme ? 238 : 204) / 255], 1.0] : null;
        $context = new RenderingContext(200, 100, null, null, null, null, ['prefers-color-scheme' => 'dark'], '', [], null, null, $lookup);
        foreach (['dark' => '#0000ee', 'light' => '#0000cc'] as $scheme => $expected) {
            $html = '<main style="columns:2;column-gap:4px;height:16px;column-fill:auto;column-rule:1px solid blue;color-scheme:'.$scheme.'"><p style="margin:0">a</p><p style="margin:0">b</p></main>';
            $result = (new HtmlRenderer())->render($html, $context);
            $rules = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
            self::assertCount(1, $rules);
            self::assertSame($expected, $rules[0]['sides']['left']['color']);
        }
    }

    public function testNamedAliasOperandsKeepTheExplicitLookupInsideColorFunctions()
    {
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.0, 0.0, 238 / 255], 1.0] : null;
        $context = new RenderingContext(200, 100, null, null, null, null, [], '', ['brand' => 'blue'], null, null, $lookup);
        $html = '<p style="color:color-mix(in srgb,brand 50%,black)">text</p>';
        $result = new HtmlRenderer()->render($html, $context);
        self::assertSame('#000077', $result->getComputedStyle($result->getDocument()->querySelector('p'))['color']);
        $default = new HtmlRenderer()->render($html, new RenderingContext(200, 100, null, null, null, null, [], '', ['brand' => 'blue']));
        self::assertSame('#000080', $default->getComputedStyle($default->getDocument()->querySelector('p'))['color']);
    }
}
