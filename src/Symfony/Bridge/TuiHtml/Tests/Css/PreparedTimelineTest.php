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
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Style\MediaContext;

class PreparedTimelineTest extends TestCase
{
    public function testActualTargetTextActivationAndRemovalOwnTheirTimeline()
    {
        $now = 0.0;
        $sheet = CssStyleSheet::fromString('p::target-text{color:#ff0000;transition:color 1s linear}p.hot::target-text{color:#0000ff}');
        $sheet->setAnimationHandlers(null, static function () use (&$now): float { return $now; });
        $view = new HtmlBuilder($sheet)->build('<p id="target">text</p>');
        $widget = $view->getById('target');
        $media = new MediaContext();
        $roleState = static function () use ($sheet): array {
            $cascade = (new \ReflectionProperty($sheet, 'cascadeResolver'))->getValue($sheet);
            $timeline = (new \ReflectionProperty($cascade, 'timeline'))->getValue($cascade);
            $elements = (new \ReflectionProperty($timeline, 'elements'))->getValue($timeline);
            foreach ($elements as $element => $state) {
                if (isset($state['roles']['target-text'])) {
                    return $state['roles']['target-text'];
                }
            }

            return [];
        };
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame([], $roleState());
        $sheet->setTargetTexts([[$widget, '/text/']]);
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame('#ff0000', $roleState()['current']['color']);
        $now = .5;
        $widget->addStyleClass('hot');
        $sheet->prepare($view->getRoot(), $media);
        $now = 1;
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame('#800080', $roleState()['current']['color']);
        self::assertTrue($sheet->isAnimating());
        $sheet->setTargetTexts([]);
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame([], $roleState());
        self::assertFalse($sheet->isAnimating());
    }

    public function testPreparedTimelineMatchesFullPreparationAcrossTicksAndMutations()
    {
        $now = 0.0;
        $views = [];
        $sheets = [];
        foreach ([0, 1] as $index) {
            $sheet = CssStyleSheet::fromString('@keyframes fade{from{color:red;width:1ch}to{color:blue;width:3ch}}p{animation:fade 1s linear infinite}p.hot{font-weight:700}p:hover{font-style:italic}');
            $sheet->setAnimationHandlers(null, static function () use (&$now): float { return $now; });
            $views[] = new HtmlBuilder($sheet)->build('<p id="target"><span>Child</span></p>');
            $sheets[] = $sheet;
        }
        $media = new MediaContext();
        foreach ([0.0, .25, .5, .75] as $step => $time) {
            $now = $time;
            $views[0]->getById('target')->addStyleClass('full-'.$step);
            foreach ($views as $index => $view) {
                $sheets[$index]->prepare($view->getRoot(), $media);
            }
            $full = $sheets[0]->getStyles($views[0]->getById('target'))[0];
            $native = $sheets[1]->getStyles($views[1]->getById('target'))[0];
            self::assertTrue($full->equals($native), 'Styles at '.$time);
            self::assertSame($sheets[0]->getComputedValue($views[0]->getById('target'), 'color'), $sheets[1]->getComputedValue($views[1]->getById('target'), 'color'));
            self::assertTrue($sheets[1]->isAnimating());
        }
        foreach ($views as $view) {
            $view->getById('target')->addStyleClass('hot');
            $view->getById('target')->setState('hover', true);
        }
        $now = .875;
        foreach ($views as $index => $view) {
            $sheets[$index]->prepare($view->getRoot(), $media);
        }
        self::assertTrue($sheets[0]->getStyles($views[0]->getById('target'))[0]->equals($sheets[1]->getStyles($views[1]->getById('target'))[0]));
        foreach (['rules', 'ua', 'device'] as $change) {
            $now += .03125;
            foreach ($sheets as $sheet) {
                if ('rules' === $change) {
                    $sheet->add('p{padding:1ch;background-color:#123456}');
                } elseif ('ua' === $change) {
                    $sheet->addUserAgentStyles('p{border:1px solid green}');
                }
            }
            if ('device' === $change) {
                $media = new MediaContext(100, 40);
            }
            foreach ($views as $index => $view) {
                $sheets[$index]->prepare($view->getRoot(), $media);
            }
            self::assertTrue($sheets[0]->getStyles($views[0]->getById('target'))[0]->equals($sheets[1]->getStyles($views[1]->getById('target'))[0]), $change);
        }
    }

    public function testGeneratedStyleInheritsTheAnimatedOwnerColor()
    {
        $now = 0.0;
        $sheet = CssStyleSheet::fromString('@keyframes tone{from{color:#000000}to{color:#ffffff}}p{animation:tone 1s linear both}p::before{content:"Prefix"}');
        $sheet->setAnimationHandlers(null, static function () use (&$now): float { return $now; });
        $view = new HtmlBuilder($sheet)->setDefaultStyles(false)->build('<p id="target">Target</p>');
        $sheet->prepare($view->getRoot(), new MediaContext());
        $now = .5;
        $sheet->prepare($view->getRoot(), new MediaContext());
        self::assertSame('#808080', $sheet->getElementStyles($view->getById('target'), 'before')[0]->getColor()->toHex());
    }
}
