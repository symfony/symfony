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
use Symfony\Component\HtmlRenderer\Animation\SizeInterpolation;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\Length;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class SizeInterpolationTest extends TestCase
{
    public function testSharedIntrinsicEndpointsKeepFractionalContentAndRespectKeywordPolicy()
    {
        $length = static fn (string $value): ?float => Length::evaluate($value, static fn (string $token): ?float => preg_match('/^([\d.]+)(?:px)?$/', $token, $match) ? (float) $match[1] : null);
        self::assertSame(['0px', '30.75px'], SizeInterpolation::ends('0', 'auto', true, 30.75, $length));
        self::assertSame(['0', 'auto'], SizeInterpolation::ends('0', 'auto', false, 30.75, $length));
        self::assertSame(['0', 'auto'], SizeInterpolation::ends('0', 'auto', true, null, $length));
        self::assertSame(['0px', '32.75px'], SizeInterpolation::ends('0', 'calc-size(auto, size + 2px)', false, 30.75, $length));
    }

    public function testHeightToAutoUsesRealLayoutAndCommitsOnlyOneTimelineTrial()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>#box{height:0;overflow:hidden;interpolate-size:allow-keywords;transition:height 1s linear}#box.open{height:auto}</style><div id="box"><div style="height:10.25px"></div><div style="height:20.5px"></div></div>', new RenderingContext(200, 200));
        $first = $session->render();
        $box = $first->getDocument()->getElementById('box');
        self::assertSame('0', $first->getComputedStyle($box)['height']);
        $box->setAttribute('class', 'open');
        self::assertSame('0px', $session->render()->getComputedStyle($box)['height']);
        $session->advance(.5);
        self::assertSame(15.375, $session->render()->getBounds($box)[0]->height);
        $session->advance(.5);
        $last = $session->render();
        self::assertSame('auto', $last->getComputedStyle($box)['height']);
        self::assertSame(30.75, $last->getBounds($box)[0]->height);
        self::assertFalse($session->isAnimating());
    }

    public function testIntrinsicTransitionLengthsUseTheSubjectsComputedFont()
    {
        $session = new HtmlRenderer()->createSession('<!DOCTYPE html><style>#box{font-size:2em;height:1em;overflow:hidden;interpolate-size:allow-keywords;transition:height 1s linear}#box.open{height:auto}</style><div id="box"><div style="height:32px"></div><div style="height:32px"></div></div>', new RenderingContext(200, 200));
        $first = $session->render();
        $box = $first->getDocument()->getElementById('box');
        self::assertSame(32.0, $first->getBounds($box)[0]->height);
        $box->setAttribute('class', 'open');
        self::assertSame('32px', $session->render()->getComputedStyle($box)['height']);
        $session->advance(.5);
        self::assertSame(48.0, $session->render()->getBounds($box)[0]->height);
    }

    public function testCompletedTransitionRestoresItsSpecifiedKeywordImmediately()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Target</p>');
        $element = $document->querySelector('p');
        $timeline = new Timeline();
        $timeline->apply($element, ['height' => '0', 'transition' => 'height 1s linear'], [], 0);
        $ends = static fn (string $property, string $from, string $to, array $style): array => ['0px', '32.75px'];
        $timeline->apply($element, ['height' => 'auto', 'transition' => 'height 1s linear', 'interpolate-size' => 'allow-keywords'], [], 0, [], [], null, $ends);
        self::assertSame('16.375px', $timeline->apply($element, ['height' => 'auto', 'transition' => 'height 1s linear', 'interpolate-size' => 'allow-keywords'], [], .5)['height']);
        self::assertSame('auto', $timeline->apply($element, ['height' => 'auto', 'transition' => 'height 1s linear', 'interpolate-size' => 'allow-keywords'], [], 1)['height']);
    }
}
