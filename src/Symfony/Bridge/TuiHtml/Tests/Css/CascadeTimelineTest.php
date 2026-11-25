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
use Symfony\Bridge\TuiHtml\Css\Animation;
use Symfony\Bridge\TuiHtml\Css\CascadeResolver;
use Symfony\Bridge\TuiHtml\Css\CssParser;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;

class CascadeTimelineTest extends TestCase
{
    public function testExplicitDiagnosticTimeUsesOnePersistentCoreTimelineAndProjectedDeviceValues()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p data-tui-oid="1"><span data-tui-oid="2">Child</span></p><aside data-tui-oid="3">Unspecified</aside>');
        $element = $document->querySelector('p');
        $child = $document->querySelector('span');
        $aside = $document->querySelector('aside');
        $parser = new CssParser();
        $rules = $parser->parse('@keyframes fade{from{color:red;font-size:10px}to{color:blue;font-size:20px}}p{animation:fade 1s ease-in both}');
        $bundle = new ParsedStylesheet($rules, $parser->takeRegisteredProperties(), $parser->takeKeyframes(), $parser->takeCounterStyles(), $parser->takeFunctions(), $parser->takePositionTries());
        $resolver = new CascadeResolver();
        $media = new MediaContext();
        $nodes = [1 => $element, 2 => $child, 3 => $aside];
        $first = $resolver->cascadeMirror($document, $nodes, $bundle, $media, [], [], [], null, null, null, null, null, null, 0.0);
        $half = $resolver->cascadeMirror($document, $nodes, $bundle, $media, [], [], [], null, null, null, null, null, null, .5);
        $animation = array_values(Animation::listFromDeclarations(['animation' => 'fade 1s ease-in both']))[0];
        $expected = $animation->interpolate($bundle->keyframes['fade'], round($animation->progress(.5) * 256) / 256, [], $media);
        self::assertSame($expected['color'], $half[$element]['computed']['color']);
        self::assertSame($half[$element]['computed']['color'], $half[$child]['computed']['color']);
        self::assertSame($expected['font-size'], $half[$element]['computed']['font-size']);
        self::assertSame($half[$element]['computed']['font-size'], $half[$child]['computed']['font-size']);
        self::assertNotSame($first[$element]['computed']['color'], $half[$element]['computed']['color']);
        self::assertArrayNotHasKey('color', $half[$aside]['computed']);
        $prepared = new CascadeResolver();
        $prepared->cascadeMirror($document, $nodes, $bundle, $media, [], [], [], null, null, null, null, null, null, 0.0);
        $reused = $prepared->advanceMirror($document, .5, $media);
        foreach ($nodes as $node) {
            self::assertSame($half[$node], $reused[$node]);
        }
        $last = $resolver->cascadeMirror($document, $nodes, $bundle, $media, [], [], [], null, null, null, null, null, null, 1.0);
        self::assertSame('blue', $last[$element]['computed']['color']);
    }

    public function testPreparedProjectionRejectsChangedDeviceContextAndSourceIdentity()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Target</p>');
        $resolver = new CascadeResolver();
        $resolver->cascadeMirror($document, [], new ParsedStylesheet(), new MediaContext(), [], [], [], null, null, null, null, null, null, 0);
        $changed = new MediaContext(100, 40);
        $this->expectException(\Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException::class);
        $resolver->advanceMirror($document, .5, $changed);
    }
}
