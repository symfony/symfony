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
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Animation\Timeline;

class TimelinePolicyTest extends TestCase
{
    public function testAnimationPoliciesPreserveLegacyQuantizationOrderAndDeviceValues()
    {
        $media = new MediaContext();
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Animated</p>');
        $element = $document->querySelector('p');
        $frames = [[0.0, ['color' => 'red', 'background-image' => 'linear-gradient(red,blue)', 'width' => '1ch', 'visibility' => 'hidden']], [1.0, ['color' => 'blue', 'background-image' => 'linear-gradient(blue,red)', 'width' => '3ch', 'visibility' => 'visible']]];
        foreach (['fade 1s ease-in .1s both', 'fade 1s steps(3,end) .1s both', 'fade 1s linear .1s both'] as $definition) {
            $values = ['animation' => $definition, 'animation-composition' => 'add', 'color' => 'black', 'width' => '2ch'];
            $animation = array_values(Animation::listFromDeclarations($values))[0];
            $timeline = $this->timeline($media);
            $timeline->apply($element, $values, ['fade' => $frames], 0.0);
            foreach ([0.0, .1, .1 + 1 / 256 - .000001, .1 + 1 / 256 + .000001, .1 + 1 / 32 - .000001, .1 + 1 / 32 + .000001, .5, 1.1, 1.2] as $time) {
                $expected = $animation->interpolate($frames, round($animation->progress($time) * 256) / 256, $values, $media) + $values;
                $actual = $timeline->apply($element, $values, ['fade' => $frames], $time);
                ksort($expected);
                ksort($actual);
                self::assertSame($expected, $actual);
            }
        }
    }

    public function testTransitionPolicyUsesLegacyEasingWithoutAnimationProgressRounding()
    {
        $media = new MediaContext();
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Transition</p>');
        $element = $document->querySelector('p');
        foreach (['red', 'linear-gradient(red,blue)', 'hidden', 'auto'] as $from) {
            $to = ['red' => 'blue', 'linear-gradient(red,blue)' => 'linear-gradient(blue,red)', 'hidden' => 'visible', 'auto' => '12px'][$from];
            $timeline = $this->timeline($media);
            $values = ['transition' => 'value 1s ease-in .1s'.(\in_array($from, ['auto', 'hidden'], true) ? ' allow-discrete' : ''), 'value' => $from];
            $timeline->apply($element, $values, [], 0.0);
            $values['value'] = $to;
            $timeline->apply($element, $values, [], .2);
            foreach ([.3, .3 + 1 / 32 - .000001, .3 + 1 / 32 + .000001, .8, 1.3] as $time) {
                $t = max(0.0, min(1.0, $time - .3));
                $expected = $t <= 0.0 ? $from : Animation::between($from, $to, Animation::ease(Animation::timing('ease-in'), $t), $media);
                self::assertSame($expected, $timeline->apply($element, $values, [], $time)['value']);
            }
        }
    }

    public function testNumericOpacityAndPaletteIndexColorsUseTheirActualPropertyTypes()
    {
        $media = new MediaContext();
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Properties</p>');
        $element = $document->querySelector('p');
        $timeline = $this->timeline($media);
        $values = ['animation' => 'fade 1s linear both', 'opacity' => '0', 'color' => '0'];
        $frames = ['fade' => [[0.0, ['opacity' => '0', 'color' => '0']], [1.0, ['opacity' => '1', 'color' => '1']]]];
        $timeline->apply($element, $values, $frames, 0);
        $half = $timeline->apply($element, $values, $frames, .5);
        self::assertSame('0.5', $half['opacity']);
        self::assertSame('#670000', $half['color']);
        $timeline = $this->timeline($media);
        $timeline->apply($element, ['opacity' => '0', 'color' => '0', 'transition' => 'all 1s linear'], [], 0);
        $targets = ['opacity' => '1', 'color' => '1', 'transition' => 'all 1s linear'];
        $timeline->apply($element, $targets, [], 1);
        $half = $timeline->apply($element, $targets, [], 1.5);
        self::assertSame('0.5', $half['opacity']);
        self::assertSame('#670000', $half['color']);
    }

    private function timeline(MediaContext $media): Timeline
    {
        return new Timeline(Animation::listFromDeclarations(...), static fn (string $a, string $b, float $t, string $property): string => Animation::between($a, $b, $t, $media, $property), static fn (string $a, string $b): string => Animation::add($a, $b, $media), static fn (string $a, string $b): bool => Animation::isInterpolable($a, $b, $media), Animation::ease(...), static fn (float $t): float => round($t * 256) / 256);
    }
}
