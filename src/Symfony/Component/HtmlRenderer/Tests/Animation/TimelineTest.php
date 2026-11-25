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

class TimelineTest extends TestCase
{
    public function testExplicitHostedMembershipIsIsolatedUntilTrialAdoption()
    {
        $document = \Dom\HTMLDocument::createEmpty();
        $root = $document->createDocumentFragment();
        $element = $document->createElement('p');
        $root->appendChild($element);
        $timeline = new Timeline();
        $values = ['animation' => 'fade 2s linear forwards'];
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        self::assertSame($values, $timeline->apply($element, $values, $frames, 0));
        self::assertFalse($timeline->hasPendingFrames(0));
        $active = new \SplObjectStorage();
        $active[$element] = true;
        $timeline->setActiveElements($active);
        self::assertSame('0', $timeline->apply($element, $values, $frames, 0)['opacity']);
        self::assertTrue($timeline->hasPendingFrames(0));
        $trial = clone $timeline;
        $trial->setActiveElements(new \SplObjectStorage());
        self::assertFalse($trial->hasPendingFrames(1));
        self::assertTrue($timeline->hasPendingFrames(1));
        $timeline->adopt($trial);
        self::assertFalse($timeline->hasPendingFrames(1));
    }

    public function testTrialFramesCannotMutateAnimationStateBeforeAdoption()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $values = ['opacity' => '0', 'transition' => 'opacity 2s linear'];
        $timeline->apply($element, $values, [], 0);
        $trial = clone $timeline;
        $trial->apply($element, array_replace($values, ['opacity' => '1']), [], 1);
        self::assertFalse($timeline->hasPendingFrames(1));
        self::assertTrue($trial->hasPendingFrames(1));
        $timeline->adopt($trial);
        $trial->forget($element);
        self::assertSame('0.5', $timeline->apply($element, array_replace($values, ['opacity' => '1']), [], 2)['opacity']);
    }

    public function testExplicitTimeDrivesKeyframesDeterministically()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $values = ['animation' => 'fade 2s linear forwards'];
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        self::assertSame('0', $timeline->apply($element, $values, $frames, 10)['opacity']);
        self::assertSame('0.5', $timeline->apply($element, $values, $frames, 11)['opacity']);
        self::assertSame('1', $timeline->apply($element, $values, $frames, 12)['opacity']);
    }

    public function testInterruptedTransitionStartsAtItsCurrentValue()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $values = ['opacity' => '0', 'transition' => 'opacity 2s linear'];
        self::assertSame('0', $timeline->apply($element, $values, [], 0)['opacity']);
        $values['opacity'] = '1';
        self::assertSame('0', $timeline->apply($element, $values, [], 1)['opacity']);
        self::assertSame('0.5', $timeline->apply($element, $values, [], 2)['opacity']);
        $values['opacity'] = '0';
        self::assertSame('0.5', $timeline->apply($element, $values, [], 2)['opacity']);
        self::assertSame('0.25', $timeline->apply($element, $values, [], 3)['opacity']);
        self::assertSame('0', $timeline->apply($element, $values, [], 4)['opacity']);
    }

    public function testInsertedElementStartsAtCurrentExplicitTime()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main></main>');
        $element = $document->createElement('p');
        $document->querySelector('main')->append($element);
        $timeline = new Timeline();
        $values = ['animation' => 'fade 2s linear forwards'];
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        self::assertSame('0', $timeline->apply($element, $values, $frames, 20)['opacity']);
        self::assertSame('0.5', $timeline->apply($element, $values, $frames, 21)['opacity']);
    }

    public function testImportantDeclarationsOverrideAnimations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade { from { opacity: 0 } to { opacity: 1 } } p { opacity: .8 !important; animation: fade 1s linear forwards }</style><p>Text</p>');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        self::assertSame('.8', $resolver->resolve($document, 100, 100, [], $timeline, 0)[$document->querySelector('p')]['opacity']);
        self::assertSame('.8', $resolver->resolve($document, 100, 100, [], $timeline, 0.5)[$document->querySelector('p')]['opacity']);
    }

    public function testPauseAndResumeRetainElapsedTime()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $values = ['animation' => 'fade 2s linear forwards'];
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        $timeline->apply($element, $values, $frames, 0);
        self::assertSame('0.5', $timeline->apply($element, $values, $frames, 1)['opacity']);
        $values['animation-play-state'] = 'paused';
        $timeline->apply($element, $values, $frames, 1);
        self::assertSame('0.5', $timeline->apply($element, $values, $frames, 2)['opacity']);
        $values['animation-play-state'] = 'running';
        $timeline->apply($element, $values, $frames, 2);
        self::assertSame('1', $timeline->apply($element, $values, $frames, 3)['opacity']);
    }

    public function testPendingFramesStopForCompletedAndPausedAnimations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        $timeline = new Timeline();
        $timeline->apply($element, ['animation' => 'fade 1s 2s linear forwards'], $frames, 0);
        self::assertTrue($timeline->hasPendingFrames(0));
        self::assertTrue($timeline->hasPendingFrames(2.5));
        self::assertFalse($timeline->hasPendingFrames(3));
        $timeline->apply($element, ['animation' => 'fade 1s linear infinite paused'], $frames, 3);
        self::assertFalse($timeline->hasPendingFrames(10));
        $timeline->apply($element, ['animation' => 'fade 1s linear infinite'], $frames, 10);
        self::assertTrue($timeline->hasPendingFrames(100));
    }

    public function testPendingFramesStopAtTransitionEnd()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $timeline->apply($element, ['opacity' => '0', 'transition' => 'opacity 1s linear'], [], 0);
        self::assertFalse($timeline->hasPendingFrames(0));
        $timeline->apply($element, ['opacity' => '1', 'transition' => 'opacity 1s linear'], [], 1);
        self::assertTrue($timeline->hasPendingFrames(1.5));
        self::assertFalse($timeline->hasPendingFrames(2));
    }

    public function testHiddenAndDetachedElementsStopRequestingFrames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Text</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $frames = ['fade' => [[0.0, ['opacity' => '0']], [1.0, ['opacity' => '1']]]];
        $values = ['animation' => 'fade 1s linear infinite'];
        $timeline->apply($element, $values, $frames, 0);
        self::assertTrue($timeline->hasPendingFrames(0));
        $timeline->apply($element, $values + ['display' => 'none'], $frames, 1);
        self::assertFalse($timeline->hasPendingFrames(1));
        $timeline->apply($element, $values, $frames, 2);
        $element->remove();
        self::assertFalse($timeline->hasPendingFrames(2));
    }

    public function testHiddenAncestorAndReducedMotionClearRetainedAnimations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes fade { from { opacity: 0 } to { opacity: 1 } } p { animation: fade 1s linear infinite }</style><section><p>Text</p></section>');
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 100, 100, [], $timeline, 0);
        self::assertTrue($timeline->hasPendingFrames(0));
        $document->querySelector('section')->setAttribute('style', 'display: none');
        $resolver->resolve($document, 100, 100, [], $timeline, 0.5);
        self::assertFalse($timeline->hasPendingFrames(0.5));
        $document->querySelector('section')->removeAttribute('style');
        $styles = $resolver->resolve($document, 100, 100, [], $timeline, 1);
        self::assertSame('0', $styles[$document->querySelector('p')]['opacity']);
        $reduced = new StyleResolver(new \Symfony\Component\HtmlRenderer\Resource\ResourceLoader(), new \Symfony\Component\HtmlRenderer\Font\FontStyle(), new \Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer(), ['prefers-reduced-motion' => 'reduce']);
        $reduced->resolve($document, 100, 100, [], $timeline, 1.5);
        self::assertFalse($timeline->hasPendingFrames(1.5));
    }
}
