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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\Animation;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class AnimationTest extends TestCase
{
    private float $now = 100.0;

    private ?Tui $tui = null;

    protected function tearDown(): void
    {
        // An animation that runs for ever keeps a timer on the event loop until the Tui stops
        $this->tui?->stop();
    }

    public function testKeyframesPreserveCustomPropertyNames()
    {
        [$tui, $view] = $this->mount('@keyframes Pulse { from, to { --Color: #123456 } } p { --Color: #ffffff; color: var(--Color); animation: Pulse 2s linear infinite }');
        $this->assertSame('#123456', $this->color($tui, $view));
    }

    public function testAnimationNamesAreCaseSensitive()
    {
        [$tui, $view] = $this->mount('@keyframes Pulse { from { color: #123456 } to { color: #ffffff } } p { animation: Pulse 2s linear infinite }');
        $this->assertSame('#123456', $this->color($tui, $view));
    }

    public function testColorsAreInterpolated()
    {
        [$tui, $view] = $this->mount('@keyframes pulse { from { color: #000000 } to { color: #ffffff } } p { animation: pulse 2s linear infinite alternate }');
        $this->assertSame('#000000', $this->color($tui, $view));
        $this->assertTrue($view->getStyleSheet()->isAnimating());

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'Halfway.');

        $this->now += 2.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'Alternate: halfway back.');
    }

    public function testStepsAndTheEnd()
    {
        [$tui, $view] = $this->mount('@keyframes blink { 0% { color: #000000 } 50% { color: #ff0000 } 100% { color: #0000ff } } p { animation-name: blink; animation-duration: 1s; animation-timing-function: steps(2); animation-fill-mode: forwards }');
        $this->assertSame('#000000', $this->color($tui, $view));

        $this->now += 0.6;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#ff0000', $this->color($tui, $view), 'The second step.');

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#0000ff', $this->color($tui, $view), 'Ended: forwards keeps the last keyframe.');
        $this->assertFalse($view->getStyleSheet()->isAnimating(), 'Nothing to draw anymore.');
    }

    public function testAMissingKeyframeIsTheInitialValue()
    {
        [$tui, $view] = $this->mount('@keyframes blink { 50% { opacity: 0 } } p { animation: blink 1s step-end infinite }');
        $style = fn () => (fn () => $this->renderer->resolveStyle($view->getById('p')))->call($tui);
        $this->assertNotTrue($style()->getDim(), 'At first, opacity is 1.');

        $this->now += 0.6;
        $tui->requestRender();
        $tui->processRender();
        $this->assertTrue($style()->getDim(), 'Past 50%, opacity is 0.');
    }

    public function testKeyframesAreBounded()
    {
        $parser = new \Symfony\Component\Tui\Css\CssParser();
        $parser->parse('@keyframes many { '.implode(', ', array_map(static fn (int $i) => ($i / 100).'%', range(0, 9999))).' { color: red } } @keyframes merged { 50% { color: red } 50% { opacity: 0 } }');
        $keyframes = $parser->takeKeyframes();

        $this->assertCount(100, $keyframes['many'], 'One block cannot have more than 100 keyframes either.');
        $this->assertSame([[0.5, ['opacity' => '0', 'color' => 'red']]], $keyframes['merged'], 'The same offset merges.');
    }

    public function testTransitions()
    {
        [$tui, $view] = $this->mount('p { color: #000000; transition: color 1s linear } p:hover { color: #ffffff }');
        $this->assertSame('#000000', $this->color($tui, $view));
        $this->assertFalse($view->getStyleSheet()->isAnimating(), 'Nothing changes at first.');

        $view->getById('p')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#000000', $this->color($tui, $view), 'It starts from the old value.');
        $this->assertTrue($view->getStyleSheet()->isAnimating());

        $this->now += 0.5;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'Halfway.');

        // Back before the end: from where it is
        $view->getById('p')->setState('hover', false);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view));
        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#000000', $this->color($tui, $view));
        $this->assertFalse($view->getStyleSheet()->isAnimating(), 'Done.');
    }

    public function testTheBackgroundShorthandTransitionsItsColor()
    {
        [$tui, $view] = $this->mount('p { background: #000000; transition: background-color 1s linear } p:hover { background: #ffffff }');
        $view->getById('p')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->now += 0.5;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', (fn () => $this->renderer->resolveStyle($view->getById('p'))->getBackground()?->toHex())->call($tui));
    }

    public function testDiscreteValuesChangeAtOnce()
    {
        [$tui, $view] = $this->mount('p { font-weight: normal; transition: all 1s } p:hover { font-weight: bold }');
        $view->getById('p')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertTrue((fn () => $this->renderer->resolveStyle($view->getById('p'))->getBold())->call($tui));
        $this->assertFalse($view->getStyleSheet()->isAnimating());
    }

    public function testAllowDiscreteChangesDiscreteValuesHalfway()
    {
        [$tui, $view] = $this->mount('p { font-weight: normal; transition: all 1s linear allow-discrete } p:hover { font-weight: bold }');
        $bold = fn (): ?bool => (fn () => $this->renderer->resolveStyle($view->getById('p'))->getBold())->call($tui);
        $view->getById('p')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertNotTrue($bold());
        $this->assertTrue($view->getStyleSheet()->isAnimating());

        $this->now += 0.4;
        $tui->requestRender();
        $tui->processRender();
        $this->assertNotTrue($bold(), 'Before halfway.');

        $this->now += 0.2;
        $tui->requestRender();
        $tui->processRender();
        $this->assertTrue($bold(), 'Past halfway.');
    }

    public function testVisibilityStaysVisibleWhileOneEndIs()
    {
        $media = new MediaContext();
        $this->assertSame('visible', Animation::between('visible', 'hidden', .9, $media));
        $this->assertSame('visible', Animation::between('hidden', 'visible', .1, $media));
        $this->assertSame('hidden', Animation::between('visible', 'hidden', 1.0, $media));
    }

    public function testListsAndFunctionsAreInterpolatedPartByPart()
    {
        $media = new MediaContext();
        $this->assertSame('5ch 2lh', Animation::between('0 1lh', '10ch 3lh', .5, $media), 'A 0 is a length of the other unit.');
        $this->assertSame('translate(2ch, -1lh)', Animation::between('translate(0ch, 0lh)', 'translate(4ch, -2lh)', .5, $media));
        $this->assertSame('1px 1px 2px #670077', Animation::between('0 0 0 red', '2px 2px 4px blue', .5, $media));
        $this->assertSame('calc(10px * 0.75 + 50% * 0.25)', Animation::between('10px', '50%', .25, $media), 'Lengths of two units, through calc().');
        $this->assertSame('rotate(45deg)', Animation::between('rotate(0deg)', 'rotate(90deg)', .5, $media));
        $this->assertSame('linear-gradient(#670077, white)', Animation::between('linear-gradient(red, white)', 'linear-gradient(blue, white)', .5, $media));

        // Of other shapes, or numbers that cannot be added, or a string: halfway
        $this->assertSame('1ch 2ch', Animation::between('1ch', '1ch 2ch', .6, $media));
        $this->assertSame('1s', Animation::between('2deg', '1s', .6, $media));
        $this->assertSame('"a 1"', Animation::between('"a 1"', '"a 9"', .4, $media));
        $this->assertSame('solid', Animation::between('solid', 'dashed', .4, $media));

        $this->assertTrue(Animation::isInterpolable('0 0', '1ch 2ch', $media));
        $this->assertFalse(Animation::isInterpolable('solid', 'dashed', $media));
        $this->assertFalse(Animation::isInterpolable('var(--a) 1ch', 'var(--a) 2ch', $media));

        // animation-composition: add, part by part
        $this->assertSame('3ch 1lh', Animation::add('1ch 0', '2ch 1lh', $media));
        $this->assertSame('calc(1ch + 2px)', Animation::add('1ch', '2px', $media));
    }

    public function testATranslationMovesThroughTheCellsBetween()
    {
        [$tui, $view] = $this->mount('p { translate: 0 0; transition: translate 1s linear } p.moved { translate: 8ch 0 }');
        $p = $view->querySelector('p');
        $column = static fn () => $tui->getWidgetRect($p)?->col;
        $this->assertSame(0, $column());

        $p->addStyleClass('moved');
        $tui->requestRender();
        $tui->processRender();
        $this->now += 0.5;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(4, $column(), 'Halfway.');
    }

    public function testStartingStyleOnFirstRender()
    {
        [$tui, $view] = $this->mount('p { color: #ffffff; transition: color 1s linear; @starting-style { color: #000000 } }');
        $this->assertSame('#000000', $this->color($tui, $view), 'Shown: from its starting style.');
        $this->assertTrue($view->getStyleSheet()->isAnimating());

        $this->now += 0.5;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'Halfway.');

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#ffffff', $this->color($tui, $view));
        $this->assertFalse($view->getStyleSheet()->isAnimating(), 'Done: no new transition from the starting style.');
    }

    public function testStartingStyleWhenShownAgain()
    {
        [$tui, $view] = $this->mount('[popover] { color: #ffffff; transition: color 1s linear } @starting-style { [popover]:popover-open { color: #000000 } }', '<p id="p" popover>text</p>');
        $this->assertFalse($view->getStyleSheet()->isAnimating(), 'Not displayed: no transition.');

        $view->showPopover($view->getById('p'));
        $tui->processRender();
        $this->assertSame('#000000', $this->color($tui, $view), 'Shown: from its starting style.');
        $this->now += 2.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#ffffff', $this->color($tui, $view));

        $view->hidePopover($view->getById('p'));
        $tui->processRender();
        $view->showPopover($view->getById('p'));
        $tui->processRender();
        $this->assertSame('#000000', $this->color($tui, $view), 'Shown again after display: none: from its starting style again.');
    }

    public function testStartingStyleIsOutOfTheCascade()
    {
        [$tui, $view] = $this->mount('@starting-style { p { color: #000000 } } p { color: #ffffff }');
        $this->assertSame('#ffffff', $this->color($tui, $view), 'Without transition, the starting style does nothing.');
        $this->assertFalse($view->getStyleSheet()->isAnimating());
    }

    #[DataProvider('provideTimingFunctions')]
    public function testTimingFunctions(string $timing, float $input, float $expected)
    {
        $function = Animation::timing($timing);
        $this->assertNotNull($function);
        // Smooth functions are cut into 32 steps
        $this->assertEqualsWithDelta($expected, Animation::ease($function, $input), 1 / 64);
    }

    public static function provideTimingFunctions(): iterable
    {
        yield 'ease' => ['ease', .5, .8024];
        yield 'ease-in' => ['ease-in', .5, .3154];
        yield 'ease-out' => ['ease-out', .5, .6846];
        yield 'ease-in-out' => ['ease-in-out', .5, .5];
        yield 'cubic-bezier like ease' => ['cubic-bezier(0.25, 0.1, 0.25, 1)', .25, .4085];
        yield 'cubic-bezier overshooting' => ['cubic-bezier(.5, -1, .5, 2)', .75, 1.1823];
        yield 'cubic-bezier linear' => ['cubic-bezier(0, 0, 1, 1)', .3, .3];
        yield 'linear' => ['linear', .3, .3];
        yield 'linear() stops' => ['linear(0, 0.25 75%, 1)', .375, .125];
        yield 'linear() after a stop' => ['linear(0, 0.25 75%, 1)', .9, .7];
        yield 'linear() spread inputs' => ['linear(0, .2, .8, 1)', 1 / 3, .2];
        yield 'linear() hold between two inputs' => ['linear(0, 0.5 25% 75%, 1)', .5, .5];
        yield 'linear() input before the previous one' => ['linear(0, 1 50%, 0 20%)', .5, 0];
        yield 'steps jump-end' => ['steps(4)', .3, .25];
        yield 'steps jump-start' => ['steps(4, jump-start)', .3, .5];
        yield 'step-start' => ['step-start', .1, 1];
        yield 'steps jump-none starts at 0' => ['steps(5, jump-none)', .1, 0];
        yield 'steps jump-none' => ['steps(5, jump-none)', .5, .5];
        yield 'steps jump-none ends at 1' => ['steps(5, jump-none)', .9, 1];
        yield 'steps jump-both' => ['steps(3, jump-both)', .1, .25];
        yield 'steps jump-both last' => ['steps(3, jump-both)', .9, .75];
        yield 'steps jump-both at the end' => ['steps(3, jump-both)', 1, 1];
    }

    public function testInvalidTimingFunctions()
    {
        $this->assertNull(Animation::timing('cubic-bezier(1.5, 0, 0, 1)'), 'The abscissas are between 0 and 1.');
        $this->assertNull(Animation::timing('cubic-bezier(0, 0, 1)'));
        $this->assertNull(Animation::timing('linear(1)'), 'linear() needs two stops.');
        $this->assertNull(Animation::timing('linear(0, 1 50px)'));
        $this->assertNull(Animation::timing('steps(1, jump-none)'), 'jump-none needs two steps.');
        $this->assertNull(Animation::timing('steps(0)'));
    }

    public function testCubicBezierAnimation()
    {
        [$tui, $view] = $this->mount('@keyframes fade { from { color: #000000 } to { color: #ffffff } } p { animation: fade 1s cubic-bezier(0, 1, 0, 1) forwards }');

        $this->now += 0.2;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#f0f0f0', $this->color($tui, $view), 'The curve rises fast: about 93% at 20%, where ease is at 30%.');
    }

    public function testEveryAnimationOfTheListRuns()
    {
        [$tui, $view] = $this->mount('@keyframes fg { from { color: #000000 } to { color: #ffffff } } @keyframes bg { from { background-color: #000000 } to { background-color: #ff0000 } } p { animation: fg 2s linear forwards, bg 1s linear forwards }');

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'The first one, halfway.');
        $this->assertSame('#ff0000', $this->background($tui, $view), 'The second one, ended.');
    }

    public function testTheLastAnimationWins()
    {
        [$tui, $view] = $this->mount('@keyframes white { from, to { color: #ffffff } } @keyframes red { from, to { color: #ff0000 } } p { animation: white 1s infinite, red 1s infinite }');
        $this->assertSame('#ff0000', $this->color($tui, $view));

        $view->getStyleSheet()->add('p { animation-name: red, white }');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#ffffff', $this->color($tui, $view));
    }

    public function testTheLonghandListsRepeat()
    {
        [$tui, $view] = $this->mount('@keyframes fg { from { color: #000000 } to { color: #ffffff } } @keyframes bg { from { background-color: #000000 } to { background-color: #ff0000 } } p { animation-name: fg, bg; animation-duration: 2s; animation-timing-function: linear; animation-fill-mode: forwards }');

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view));
        $this->assertSame('#800000', $this->background($tui, $view), 'The one duration is that of both.');
    }

    public function testAnAnimationKeepsGoingWhenAnotherOneIsAdded()
    {
        [$tui, $view] = $this->mount('@keyframes fg { from { color: #000000 } to { color: #ffffff } } @keyframes bg { from { background-color: #000000 } to { background-color: #ff0000 } } p { animation: fg 2s linear forwards } p.both { animation: bg 2s linear forwards, fg 2s linear forwards }');

        $this->now += 1.0;
        $tui->requestRender();
        $tui->processRender();
        $view->getById('p')->addStyleClass('both');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('#808080', $this->color($tui, $view), 'fg is still halfway.');
        $this->assertSame('#000000', $this->background($tui, $view), 'bg starts.');
    }

    public function testAnUnknownAnimationDoesNothing()
    {
        [, $view] = $this->mount('p { animation: nowhere 1s infinite; color: #123456 }');
        $this->assertFalse($view->getStyleSheet()->isAnimating());
    }

    /**
     * @return array{Tui, HtmlView}
     */
    private function mount(string $css, string $html = '<p id="p">text</p>'): array
    {
        $sheet = new CssStyleSheet();
        $sheet->setAnimationHandlers(null, fn (): float => $this->now);
        $view = (new HtmlBuilder($sheet))->build('<style>'.$css.'</style>'.$html);
        $tui = $this->tui = new Tui(terminal: new VirtualTerminal(20, 4));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $view];
    }

    private function background(Tui $tui, HtmlView $view): ?string
    {
        $widget = $view->getById('p');

        return (fn () => $this->renderer->resolveStyle($widget)->getBackground()?->toHex())->call($tui);
    }

    private function color(Tui $tui, HtmlView $view): ?string
    {
        $widget = $view->getById('p');

        return (fn () => $this->renderer->resolveStyle($widget)->getColor()?->toHex())->call($tui);
    }

    public function testScrollDrivenAnimations()
    {
        $this->assertSame('scroll(self)', Animation::fromDeclarations(['animation' => 'grow linear', 'animation-timeline' => 'SCROLL(SELF)'])->timeline);
        $this->assertSame('--MyTimeline', Animation::fromDeclarations(['animation' => 'grow linear', 'animation-timeline' => '--MyTimeline'])->timeline);
        $this->assertSame('Grow', Animation::fromDeclarations(['animation-name' => 'Grow', 'animation-duration' => '1s'])->name);

        $animation = Animation::fromDeclarations(['animation' => 'grow linear', 'animation-timeline' => 'scroll()']);
        $this->assertNotNull($animation, 'A scroll timeline needs no duration.');
        $this->assertTrue($animation->isScrollDriven());
        $this->assertSame(0.25, $animation->progressAt(0.25));
        $this->assertSame(1.0, $animation->progressAt(1.0), 'The end of the timeline is in it.');
        $this->assertNull($animation->progressAt(1.5));
        $this->assertNull($animation->progressAt(-0.1));

        $filled = Animation::fromDeclarations(['animation' => 'grow linear both reverse', 'animation-timeline' => 'view()']);
        $this->assertSame(1.0, $filled->progressAt(-1.0), 'Before its range, filled backwards, reversed.');
        $this->assertSame(0.0, $filled->progressAt(2.0));

        $this->assertNull(Animation::fromDeclarations(['animation' => 'grow linear']), 'Without a timeline, a duration is needed.');
        $this->assertFalse(Animation::fromDeclarations(['animation' => 'grow 1s'])->isScrollDriven());
    }

    public function testAnimationRange()
    {
        $range = static fn (array $declarations) => Animation::fromDeclarations(['animation' => 'grow', 'animation-timeline' => 'view()'] + $declarations)->range;

        $this->assertSame([['normal', 0.0], ['normal', 100.0]], $range([]));
        $this->assertSame([['entry', 0.0], ['entry', 100.0]], $range(['animation-range' => 'entry']));
        $this->assertSame([['entry', 10.0], ['exit', 90.0]], $range(['animation-range' => 'entry 10% exit 90%']));
        $this->assertSame([['normal', 20.0], ['normal', 80.0]], $range(['animation-range' => '20% 80%']));
        $this->assertSame([['cover', 50.0], ['contain', 100.0]], $range(['animation-range-start' => 'cover 50%', 'animation-range-end' => 'contain']));
    }

    public function testThePageMountedNextOfTheSameStyleSheetKeepsItsAnimationsDrawn()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $tui = new Tui(terminal: new VirtualTerminal(40, 5));
        $tui->start();
        $page = '<style>@keyframes pulse { from { color: #ff0000 } to { color: #0000ff } } p { animation: pulse 1s infinite }</style><p>%s</p>';
        $builder->build(\sprintf($page, 'A'))->mount($tui);
        $tui->processRender();

        // The next page replaces it while the style sheet animates: its frames still scheduled
        $builder->build(\sprintf($page, 'B'))->mount($tui);
        $tui->processRender();
        $scheduler = new \ReflectionProperty(Tui::class, 'tickScheduler')->getValue($tui);
        $this->assertNotNull($scheduler->getNextDelay());
        $tui->stop();
    }

    public function testAStyleSheetMountedOnTwoTuisDrawsTheAnimationsOfBoth()
    {
        $builder = new HtmlBuilder(new CssStyleSheet());
        $page = '<style>@keyframes pulse { from { color: #ff0000 } to { color: #0000ff } } p { animation: pulse 1s infinite }</style><p>%s</p>';
        $first = new Tui(terminal: new VirtualTerminal(40, 5));
        $first->start();
        $builder->build(\sprintf($page, 'A'))->mount($first);
        $first->processRender();
        $second = new Tui(terminal: new VirtualTerminal(40, 5));
        $second->start();
        $builder->build(\sprintf($page, 'B'))->mount($second);
        $second->processRender();

        // Each Tui its interval
        foreach ([$first, $second] as $tui) {
            $this->assertNotNull(new \ReflectionProperty(Tui::class, 'tickScheduler')->getValue($tui)->getNextDelay());
        }
        $first->stop();
        $second->stop();
    }
}
