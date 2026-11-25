<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class AppliedPropertiesTest extends TestCase
{
    public function testEqualInitialValuesAreAppliedAndExpiredValuesAreCleared()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Target</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $values = ['opacity' => '1', 'animation' => 'fade 1s linear'];
        $frames = ['fade' => [[0.0, ['opacity' => '1']], [1.0, ['opacity' => '1']]]];
        $timeline->apply($element, $values, $frames, 0);
        self::assertSame(['opacity'], $timeline->getAppliedProperties($element));
        $timeline->apply($element, $values, $frames, 1);
        self::assertSame([], $timeline->getAppliedProperties($element));
        $timeline->apply($element, ['opacity' => '1'], $frames, 2);
        self::assertSame([], $timeline->getAppliedProperties($element));
        $timeline = new Timeline();
        $timeline->apply($element, ['opacity' => '0', 'transition' => 'opacity 1s linear'], [], 3);
        $timeline->apply($element, ['opacity' => '1', 'transition' => 'opacity 1s linear'], [], 3);
        self::assertSame(['opacity'], $timeline->getAppliedProperties($element));
        $timeline->apply($element, ['opacity' => '1', 'transition' => 'opacity 1s linear'], [], 4);
        self::assertSame([], $timeline->getAppliedProperties($element));
    }

    public function testComputedInheritanceAndValueDependenciesCarryOnlyAppliedProperties()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:black}@property --blocked{syntax:"<color>";inherits:false;initial-value:black}@keyframes change{from{color:red;font-size:10px;--tone:red;--blocked:red}to{color:blue;font-size:20px;--tone:blue;--blocked:blue}}div{animation:change 1s linear both}.inherit{color:inherit}.unset{color:unset}.revert{color:revert}.current{color:currentColor;background-color:currentColor}.relative{font-size:1em}.variable{color:var(--tone)}.absolute{color:green!important;font-size:12px}.fallback{color:var(--missing,var(--tone))}.static{--fixed:green;color:var(--fixed,var(--tone))}.alias{--alias:var(--tone);color:var(--alias)}.blocked{color:var(--blocked)}.revert-ua{color:revert}.important-inherit{color:inherit!important}</style><div><span class="inherit"></span><span class="unset"></span><span class="revert"></span><span class="current"></span><span class="relative"></span><span class="variable"></span><span class="absolute"></span><span class="fallback"></span><span class="static"></span><span class="alias"></span><span class="blocked"></span><span class="revert-ua"></span><span class="important-inherit"></span></div><p>Unanimated</p>');
        $resolver = new StyleResolver();
        $timeline = new Timeline();
        $states = [[$document->querySelector('.revert-ua'), ['user-agent-styles' => ['' => ['color' => 'green']]]]];
        $initial = $resolver->resolve($document, 400, 200, $states, $timeline, 0);
        self::assertSame('red', $initial[$document->querySelector('div')]['color']);
        $styles = $resolver->resolve($document, 400, 200, $states, $timeline, .5);
        foreach (['inherit', 'unset', 'revert', 'current', 'variable', 'fallback', 'alias', 'important-inherit'] as $class) {
            $node = $document->querySelector('.'.$class);
            self::assertSame('#800080', $styles[$node]['color']);
            self::assertArrayHasKey('color', $resolver->getCascadeRecord($node)['animated'], $class);
        }
        self::assertArrayHasKey('background-color', $resolver->getCascadeRecord($document->querySelector('.current'))['animated']);
        $relative = $document->querySelector('.relative');
        self::assertSame('15px', $styles[$relative]['font-size']);
        self::assertArrayHasKey('font-size', $resolver->getCascadeRecord($relative)['animated']);
        $absolute = $resolver->getCascadeRecord($document->querySelector('.absolute'))['animated'];
        self::assertArrayNotHasKey('color', $absolute);
        self::assertArrayNotHasKey('font-size', $absolute);
        self::assertArrayNotHasKey('color', $resolver->getCascadeRecord($document->querySelector('.blocked'))['animated']);
        self::assertSame('black', $styles[$document->querySelector('.blocked')]['color']);
        self::assertArrayNotHasKey('color', $resolver->getCascadeRecord($document->querySelector('.revert-ua'))['animated']);
        self::assertSame('green', $styles[$document->querySelector('.revert-ua')]['color']);
        self::assertArrayNotHasKey('color', $resolver->getCascadeRecord($document->querySelector('.static'))['animated']);
        self::assertSame('green', $styles[$document->querySelector('.static')]['color']);
        self::assertSame([], $resolver->getCascadeRecord($document->querySelector('p'))['animated']);
        $resolver->resolve($document, 400, 200);
        self::assertArrayNotHasKey('animated', $resolver->getCascadeRecord($document->querySelector('div')));
    }

    public function testCurrentColorTracksTheAnimatedForegroundWithoutOverridingItsOwnAnimation()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p{color:white;border:1px solid currentColor;outline-color:color-mix(in srgb,currentColor 50%,black);transition:color 1s linear}@starting-style{p{color:black}}.own{transition:color 1s linear,border-top-color 1s linear;border-top-color:white}@starting-style{.own{border-top-color:red}}</style><p>Target</p><p class="own">Own</p>');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $element = $document->querySelector('p');
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('black', $styles[$element]['border-left-color']);
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('#808080', $styles[$element]['border-left-color']);
        self::assertSame('#404040', $styles[$element]['outline-color']);
        self::assertSame('#ff8080', $styles[$document->querySelector('.own')]['border-top-color']);
    }

    public function testCurrentColorTracksForegroundDrivenByAnAnimatedRegisteredVariable()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --tone{syntax:"<color>";inherits:true;initial-value:white}@keyframes tone{from{--tone:black}to{--tone:white}}@keyframes edge{from{border-top-color:red}to{border-top-color:white}}p{color:var(--tone);border:1px solid currentColor;outline-color:color-mix(in srgb,currentColor 50%,black);animation:tone 1s linear both}.own{animation:tone 1s linear both,edge 1s linear both}.fixed{color:green!important}</style><p>Target</p><p class="own">Own</p><p class="fixed">Fixed</p>');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $element = $document->querySelector('p');
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertSame('black', $styles[$element]['border-left-color']);
        $styles = $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('#808080', $styles[$element]['border-left-color']);
        self::assertSame('#404040', $styles[$element]['outline-color']);
        self::assertSame('#ff8080', $styles[$document->querySelector('.own')]['border-top-color']);
        self::assertSame('green', $styles[$document->querySelector('.fixed')]['border-left-color']);
    }
}
