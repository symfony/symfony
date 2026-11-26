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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class ResolvedStyleSemanticsTest extends TestCase
{
    #[DataProvider('provideOwnerDeclarations')]
    public function testRepeatedInactivePseudoStylesStillEvaluateEveryValueCallback(string $ownerDeclarations)
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>'.$ownerDeclarations.'::target-text{color:--dynamic();background-color:MarkText}</style>'.str_repeat('<p>Target</p>', 30));
        $source = $document->saveHtml();
        $calls = 0;
        $color = 'red';
        $resolve = static function (string $value, array $scope) use (&$calls, &$color): string {
            ++$calls;

            return $color;
        };
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new MonospaceTextMeasurer(), [], '', [], null, $resolve);
        foreach (['red', 'blue', 'green'] as $frame => $color) {
            $styles = $resolver->resolve($document, 400, 200);
            foreach ($document->querySelectorAll('p') as $owner) {
                self::assertSame($color, $resolver->getPseudoStyle($owner, 'target-text')['color']);
                $record = $resolver->getCascadeRecord($owner);
                self::assertSame($styles[$owner], $record['computed']);
                self::assertSame($color, $record['pseudos']['target-text']['resolved']['color']);
                self::assertSame('author', $record['pseudos']['target-text']['declarations']['color']['origin']);
            }
            self::assertSame(34 * ($frame + 1), $calls);
        }
        self::assertSame(102, $calls);
        self::assertSame($source, $document->saveHtml());
    }

    public static function provideOwnerDeclarations(): iterable
    {
        yield 'undeclared owners' => [''];
        yield 'authored owners' => ['p{font-size:18px;line-height:1.5}'];
    }

    public function testAuthoredOwnersKeepExplicitAndInheritedLineHeightOriginsDistinct()
    {
        foreach ([false, true] as $reverse) {
            $explicit = '<div id="explicit" style="font-size:20px;line-height:20px">Explicit</div>';
            $inherited = '<div id="inherited" style="font-size:20px">Inherited</div>';
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><section style="font-size:10px;line-height:2">'.($reverse ? $inherited.$explicit : $explicit.$inherited).'</section>');
            $resolver = new StyleResolver();
            $styles = $resolver->resolve($document, 400, 200);
            $explicitOwner = $document->getElementById('explicit');
            $inheritedOwner = $document->getElementById('inherited');
            self::assertSame('20px', $styles[$explicitOwner]['font-size']);
            self::assertSame('20px', $styles[$inheritedOwner]['font-size']);
            self::assertSame('20px', $styles[$explicitOwner]['line-height']);
            self::assertSame('40px', $styles[$inheritedOwner]['line-height']);
            self::assertSame('20px', $resolver->getCascadeRecord($explicitOwner)['declarations']['line-height']['value']);
            self::assertArrayNotHasKey('line-height', $resolver->getCascadeRecord($inheritedOwner)['declarations']);
        }
    }

    public function testInheritedFontRootUnitsLineHeightAndCustomColorsRemainDistinct()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>::target-text{color:currentColor;background-color:var(--tone)}</style><section style="font-size:2rem;line-height:1.5;color:blue;--tone:red"><span id="a">A</span><span id="b">B</span></section><section style="font-size:12px;line-height:2;color:red;--tone:green"><span id="c">C</span><span id="d">D</span></section>');
        $resolver = new StyleResolver();
        foreach ([10, 20] as $rootSize) {
            $document->documentElement->setAttribute('style', 'font-size:'.$rootSize.'px');
            $styles = $resolver->resolve($document, 400, 200);
            foreach (['a', 'b', 'c', 'd'] as $id) {
                $owner = $document->getElementById($id);
                $size = \in_array($id, ['a', 'b'], true) ? 2 * $rootSize : 12;
                $line = \in_array($id, ['a', 'b'], true) ? 3 * $rootSize : 24;
                self::assertSame($size.'px', $styles[$owner]['font-size']);
                self::assertSame($line.'px', $styles[$owner]['line-height']);
                self::assertSame($size.'px', $resolver->getPseudoStyle($owner, 'target-text')['font-size']);
                self::assertSame($line.'px', $resolver->getPseudoStyle($owner, 'target-text')['line-height']);
                self::assertSame(\in_array($id, ['a', 'b'], true) ? 'red' : 'green', $resolver->getPseudoStyle($owner, 'target-text')['background-color']);
                self::assertArrayNotHasKey('font-size', $resolver->getCascadeRecord($owner)['declarations']);
            }
        }
    }

    public function testInactiveNeighborsDoNotShareAnActiveTargetsAnimationState()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>::target-text{color:red}#b::target-text{transition:color 1s linear}p:focus::target-text{color:blue}</style><p id="a">A</p><p id="b">B</p><p id="c">C</p>');
        $owner = $document->getElementById('b');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $active = ['active-pseudo-elements' => ['target-text']];
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [[$owner, $active]], $timeline, 1);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus', ...$active]]], $timeline, 1.5);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus', ...$active]]], $timeline, 2);
        self::assertSame('#800080', $resolver->getPseudoStyle($owner, 'target-text')['color']);
        self::assertTrue($timeline->hasPendingFrames(2));
        foreach (['a', 'c'] as $id) {
            $neighbor = $document->getElementById($id);
            self::assertSame('red', $resolver->getPseudoStyle($neighbor, 'target-text')['color']);
            self::assertSame([], $timeline->getAppliedProperties($neighbor, 'target-text'));
        }
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, 2.1);
        self::assertFalse($timeline->hasPendingFrames(2.1));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'target-text'));
    }

    public function testDeactivatedPseudoDropsItsAnimationWhileInactiveNeighborsStayVisibleToInspection()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>@keyframes pulse{from{color:red}to{color:blue}}::target-text{color:red}#b:focus::target-text{animation:pulse 2s linear}</style><p id="a">A</p><p id="b">B</p><p id="c">C</p>');
        $owner = $document->getElementById('b');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $active = ['focus', 'active-pseudo-elements' => ['target-text']];
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [[$owner, $active]], $timeline, 1);
        $resolver->resolve($document, 400, 200, [[$owner, $active]], $timeline, 2);
        self::assertSame('#800080', $resolver->getPseudoStyle($owner, 'target-text')['color']);
        self::assertTrue($timeline->hasPendingFrames(2));
        $resolver->resolve($document, 400, 200, [], $timeline, 2.1);
        self::assertFalse($timeline->hasPendingFrames(2.1));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'target-text'));
        foreach (['a', 'b', 'c'] as $id) {
            $element = $document->getElementById($id);
            self::assertSame('red', $resolver->getPseudoStyle($element, 'target-text')['color']);
            self::assertSame('red', $resolver->getCascadeRecord($element)['pseudos']['target-text']['computed']['color']);
        }
    }
}
