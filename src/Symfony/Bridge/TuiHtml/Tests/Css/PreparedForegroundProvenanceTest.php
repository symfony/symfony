<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CascadeResolver;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\CssRule;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;

class PreparedForegroundProvenanceTest extends TestCase
{
    public function testOnlyTheImplicitEmbeddingForegroundIsAbsentFromNativePartPaint()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><input>');
        $root = $document->documentElement;
        $input = $document->querySelector('input');
        $parser = new CssParser();
        $rules = $parser->parse('input::track{opacity:.5}');
        $style = new ParsedStylesheet($rules);
        $full = null;
        $capture = static function (\SplObjectStorage $styles) use (&$full, $input): void { $full = $styles[$input]; };
        $resolver = new CascadeResolver();
        $projected = $resolver->cascadeMirror($document, [], $style, new MediaContext(), [], [], [], null, null, null, $capture, '#e5e5e5');
        self::assertSame('#e5e5e5', $full['color']);
        self::assertArrayNotHasKey('color', $projected[$input]['pseudos']['track']['computed']);
        self::assertSame('.5', $projected[$input]['pseudos']['track']['computed']['opacity']);

        $authored = new ParsedStylesheet([...$rules, ...$parser->parse('html{color:#e5e5e5}')]);
        $projected = $resolver->cascadeMirror($document, [], $authored, new MediaContext(), [], [], [], null, null, null, null, '#e5e5e5');
        self::assertSame('#e5e5e5', $projected[$input]['pseudos']['track']['computed']['color']);

        $native = new \SplObjectStorage();
        $native[$root] = ['' => ['color' => '#e5e5e5']];
        $projected = $resolver->cascadeMirror($document, [], $style, new MediaContext(), [], [], [], null, null, null, null, '#e5e5e5', $native);
        self::assertSame('#e5e5e5', $projected[$input]['pseudos']['track']['computed']['color']);

        $ua = $parser->parse('html{color:#e5e5e5!important}')[0];
        $explicitUa = new CssRule($ua->selector, $ua->declarations, [], 1, CssRule::UNLAYERED, true);
        $projected = $resolver->cascadeMirror($document, [], new ParsedStylesheet([...$rules, $explicitUa]), new MediaContext(), [], [], [], null, null, null, null, '#e5e5e5');
        self::assertSame('#e5e5e5', $projected[$input]['pseudos']['track']['computed']['color']);
    }

    public function testAnimatedForegroundIsExplicitEvenWhenItEqualsTheEmbeddingDefault()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><input>');
        $input = $document->querySelector('input');
        $parser = new CssParser();
        $rules = $parser->parse('@keyframes same{from,to{color:#e5e5e5}}html{animation:same 1s linear both}input::track{opacity:.5}');
        $bundle = new ParsedStylesheet($rules, [], $parser->takeKeyframes());
        $projected = new CascadeResolver()->cascadeMirror($document, [], $bundle, new MediaContext(), [], [], [], null, null, null, null, '#e5e5e5', null, 0);
        self::assertSame('#e5e5e5', $projected[$input]['pseudos']['track']['computed']['color']);
    }
}
