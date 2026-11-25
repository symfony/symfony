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

class PseudoTimelineTest extends TestCase
{
    public function testTargetTextLifecycleOnlyRetainsActiveRanges()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>::target-text{color:red;transition:color 1s linear}p:focus::target-text{color:blue}</style><p>Target</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $elements = (new \ReflectionProperty($timeline, 'elements'))->getValue($timeline);
        self::assertArrayNotHasKey('target-text', $elements[$owner]['roles'] ?? []);
        self::assertSame('red', $resolver->getPseudoStyle($owner, 'target-text')['color']);
        $active = ['active-pseudo-elements' => ['target-text']];
        $resolver->resolve($document, 400, 200, [[$owner, $active]], $timeline, 1);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus', ...$active]]], $timeline, 1.5);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus', ...$active]]], $timeline, 2);
        self::assertSame('#800080', $resolver->getPseudoStyle($owner, 'target-text')['color']);
        self::assertTrue($timeline->hasPendingFrames(2));
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, 2.1);
        self::assertFalse($timeline->hasPendingFrames(2.1));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'target-text'));
        $resolver->resolve($document, 400, 200, [[$owner, $active]], $timeline, 3);
        self::assertSame('red', $resolver->getPseudoStyle($owner, 'target-text')['color']);
    }

    public function testPrincipalBeforeAndAfterKeepIndependentTransitionsAndAppliedProperties()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade{from{opacity:0}to{opacity:1}}p{animation:fade 1s linear both}p::before{content:"B";color:red;transition:color 1s linear}p:focus::before{color:blue}p::after{content:"A";opacity:.25;transition:opacity 1s linear}p:focus::after{opacity:.75}</style><p>Target</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, .25);
        self::assertSame('red', $resolver->getPseudoStyle($owner, 'before')['color']);
        $styles = $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, .75);
        self::assertSame('0.75', $styles[$owner]['opacity']);
        self::assertSame('#800080', $resolver->getPseudoStyle($owner, 'before')['color']);
        self::assertSame('0.5', $resolver->getPseudoStyle($owner, 'after')['opacity']);
        self::assertSame(['opacity'], $timeline->getAppliedProperties($owner));
        self::assertSame(['color'], $timeline->getAppliedProperties($owner, 'before'));
        self::assertSame(['opacity'], $timeline->getAppliedProperties($owner, 'after'));
        self::assertTrue($timeline->hasPendingFrames(.75));
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, 1.25);
        self::assertSame('blue', $resolver->getPseudoStyle($owner, 'before')['color']);
        self::assertSame('.75', $resolver->getPseudoStyle($owner, 'after')['opacity']);
        self::assertFalse($timeline->hasPendingFrames(1.25));
    }

    public function testPseudoImportantDeclarationsAndRegisteredInitialValuesBeatItsAnimation()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@property --alpha{syntax:"<number>";inherits:false;initial-value:.25}@keyframes shade{from{color:red;opacity:0}to{color:blue;opacity:1}}p{--alpha:.9}p::before{content:"B";color:green!important;opacity:var(--alpha)!important;animation:shade 1s linear both}</style><p>Target</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertSame('green', $resolver->getPseudoStyle($owner, 'before')['color']);
        self::assertSame('.25', $resolver->getPseudoStyle($owner, 'before')['opacity']);
        self::assertSame([], $timeline->getAppliedProperties($owner));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'before'));
    }

    public function testAnExistingRoleDoesNotReplaceThePrincipalsStartingStyle()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Owner</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $timeline->apply($owner, ['color' => 'red'], [], 0, [], [], null, null, 'before');
        $principal = $timeline->apply($owner, ['opacity' => '1', 'transition' => 'opacity 1s linear'], [], 0, [], ['opacity' => '0']);
        self::assertSame('0', $principal['opacity']);
        self::assertSame(['opacity'], $timeline->getAppliedProperties($owner));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'before'));
    }

    public function testRemovedRolesAndHiddenOwnersStopTheirPendingFrames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before{content:"B";color:red;transition:color 1s linear}p:focus::before{color:blue}</style><p>Target</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, 1);
        self::assertTrue($timeline->hasPendingFrames(1));
        $document->querySelector('style')->textContent = 'p{color:green}';
        $resolver->resolve($document, 400, 200, [], $timeline, 1.1);
        self::assertFalse($timeline->hasPendingFrames(1.1));
        self::assertSame([], $timeline->getAppliedProperties($owner, 'before'));
    }

    public function testHiddenAndContentNoneRolesResetWithoutErasingOtherOwnerState()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before{content:"B";color:red;transition:color 1s linear}p:focus::before{color:blue}</style><p>Target</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, .1);
        self::assertTrue($timeline->hasPendingFrames(.1));
        $owner->setAttribute('style', 'display:none');
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, .2);
        self::assertFalse($timeline->hasPendingFrames(.2));
        $owner->removeAttribute('style');
        $resolver->resolve($document, 400, 200, [[$owner, ['focus']]], $timeline, .3);
        self::assertSame('blue', $resolver->getPseudoStyle($owner, 'before')['color']);
        $resolver->resolve($document, 400, 200, [], $timeline, .4);
        self::assertTrue($timeline->hasPendingFrames(.4));
        $document->querySelector('style')->textContent .= 'p::before{content:none}';
        $resolver->resolve($document, 400, 200, [], $timeline, .5);
        self::assertFalse($timeline->hasPendingFrames(.5));
    }

    public function testRolesUseStableSourceIdentityAcrossVisualDocumentReconstruction()
    {
        $source = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Source</p>');
        $owner = $source->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        foreach ([[0.0, false, 'red'], [0.0, true, 'red'], [.5, true, '#800080']] as [$time, $focus, $expected]) {
            $visual = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p::before{content:"B";color:red;transition:color 1s linear}p:focus::before{color:blue}</style><p>Visual</p>');
            $node = $visual->querySelector('p');
            $identities = new \SplObjectStorage();
            $identities[$node] = $owner;
            $resolver->resolve($visual, 400, 200, [[$node, $focus ? ['focus'] : []]], $timeline, $time, null, null, $identities);
            self::assertSame($expected, $resolver->getPseudoStyle($node, 'before')['color']);
        }
        self::assertSame(['color'], $timeline->getAppliedProperties($owner, 'before'));
        self::assertSame([], $timeline->getAppliedProperties($node, 'before'));
        $active = new \SplObjectStorage();
        $timeline->setActiveElements($active);
        self::assertFalse($timeline->hasPendingFrames(.5));
    }

    public function testOwnerlessMarkersDoNotAnimateAndGroupRemovalResetsThem()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes pulse{from{opacity:0}to{opacity:1}}p::scroll-marker{content:"M";animation:pulse 1s linear both}</style><div id="group"><p>Target</p></div>');
        $owner = $document->querySelector('p');
        $group = $document->getElementById('group');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200, [], $timeline, 0);
        self::assertFalse($timeline->hasPendingFrames(0));
        $group->setAttribute('style', 'overflow:auto;scroll-marker-group:after');
        $resolver->resolve($document, 400, 200, [], $timeline, 1);
        self::assertSame('0', $resolver->getPseudoStyle($owner, 'scroll-marker')['opacity']);
        $resolver->resolve($document, 400, 200, [], $timeline, 1.5);
        self::assertSame('0.5', $resolver->getPseudoStyle($owner, 'scroll-marker')['opacity']);
        $group->removeAttribute('style');
        $resolver->resolve($document, 400, 200, [], $timeline, 1.6);
        self::assertFalse($timeline->hasPendingFrames(1.6));
        $group->setAttribute('style', 'overflow:auto;scroll-marker-group:before');
        $resolver->resolve($document, 400, 200, [], $timeline, 2);
        self::assertSame('0', $resolver->getPseudoStyle($owner, 'scroll-marker')['opacity']);
    }

    public function testRoleStateDoesNotRetainItsNativeOwner()
    {
        $timeline = new Timeline();
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Owner</p>');
        $owner = $document->querySelector('p');
        $weak = \WeakReference::create($owner);
        $timeline->apply($owner, ['opacity' => '0', 'transition' => 'opacity 1s linear'], [], 0, [], [], null, null, 'before');
        $timeline->apply($owner, ['opacity' => '1', 'transition' => 'opacity 1s linear'], [], 0, [], [], null, null, 'before');
        self::assertTrue($timeline->hasPendingFrames(0));
        unset($owner, $document);
        gc_collect_cycles();
        self::assertNull($weak->get());
        self::assertFalse($timeline->hasPendingFrames(0));
    }

    public function testChangingRoleNamesDoesNotAccumulateOldMembership()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style></style><p>Owner</p>');
        $owner = $document->querySelector('p');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        for ($index = 0; $index < 16; ++$index) {
            $document->querySelector('style')->textContent = 'p::highlight(phase'.$index.'){background-color:red}';
            $resolver->resolve($document, 400, 200, [], $timeline, $index);
            $states = new \ReflectionProperty($timeline, 'elements')->getValue($timeline);
            self::assertLessThanOrEqual(2, \count($states[$owner]['roles'] ?? []));
        }
        $timeline->forget($owner);
        $states = new \ReflectionProperty($timeline, 'elements')->getValue($timeline);
        self::assertFalse(isset($states[$owner]));
    }
}
