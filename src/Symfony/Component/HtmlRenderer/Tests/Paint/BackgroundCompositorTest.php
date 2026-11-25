<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Paint\BackgroundCompositor;

final class BackgroundCompositorTest extends TestCase
{
    public function testIsolatedGroupUsesOnlyItsOwnBackdropAndPremultipliedAlpha()
    {
        $rect = ['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 10.0, 'height' => 10.0];
        self::assertSame([[1.0, 0.0, 0.0], 0.5], BackgroundCompositor::sample([$rect + ['color' => 'rgb(255 0 0 / .5)', 'blend-mode' => 'multiply']], 1, 1, static fn () => null));
        $group = [$rect + ['color' => 'blue'], $rect + ['color' => 'rgb(255 0 0 / .5)', 'blend-mode' => 'screen']];
        self::assertSame([[0.5, 0.0, 1.0], 1.0], BackgroundCompositor::sample($group, 1, 1, static fn () => null));
    }

    public function testLayerClipsAndTextMaskAlphaUseTheSameOrderedComposition()
    {
        $rect = ['type' => 'rectangle', 'x' => 0.0, 'y' => 0.0, 'width' => 10.0, 'height' => 10.0];
        $group = [$rect + ['color' => 'white'], ['type' => 'push-clip', 'x' => 2.0, 'y' => 0.0, 'width' => 2.0, 'height' => 10.0], $rect + ['color' => 'rgb(255 0 0 / .5)', 'text-mask' => [], 'blend-mode' => 'multiply'], ['type' => 'pop-clip']];
        self::assertSame([[1.0, 1.0, 1.0], 1.0], BackgroundCompositor::sample($group, 3, 1, static fn () => null));
        self::assertSame([[1.0, 0.5, 0.5], 1.0], BackgroundCompositor::sample($group, 3, 1, static fn () => null, true));
        self::assertSame([[1.0, 1.0, 1.0], 1.0], BackgroundCompositor::sample($group, 1, 1, static fn () => null, true));
    }

    public function testPainterGroupsOnlyNonNormalBackgroundModesAndKeepsBordersOutside()
    {
        $result = new \Symfony\Component\HtmlRenderer\HtmlRenderer()->render('<!doctype html><div style="width:10px;height:10px;border:1px solid;background:linear-gradient(red,red),linear-gradient(blue,blue);background-blend-mode:screen,multiply"></div>', new \Symfony\Component\HtmlRenderer\RenderingContext(100, 100));
        $operations = $result->getDisplayList()->getOperations();
        self::assertSame(['background-group', 'border'], array_column($operations, 'type'));
        self::assertSame(['multiply', 'screen'], array_column($operations[0]['layers'], 'blend-mode'));
        self::assertIsString(json_encode($operations, \JSON_THROW_ON_ERROR));
    }

    public function testNormalBackgroundsKeepTheExistingSimpleDisplayList()
    {
        foreach (['normal' => ['gradient'], 'multiply' => ['background-group']] as $mode => $types) {
            $result = new \Symfony\Component\HtmlRenderer\HtmlRenderer()->render('<!doctype html><div style="width:10px;height:10px;background:linear-gradient(red,blue);background-blend-mode:'.$mode.'"></div>', new \Symfony\Component\HtmlRenderer\RenderingContext(100, 100));
            self::assertSame($types, array_column($result->getDisplayList()->getOperations(), 'type'));
        }
    }

    public function testMalformedClipStateCannotEscapeItsIsolatedGroup()
    {
        $this->expectException(\InvalidArgumentException::class);
        BackgroundCompositor::sample([['type' => 'pop-clip']], 1, 1, static fn () => null);
    }

    public function testWorkBudgetIsCheckedBeforeResourceCallbacks()
    {
        $this->expectException(\LengthException::class);
        BackgroundCompositor::sample(array_fill(0, 129, ['type' => 'background-image']), 1, 1, static function () { throw new \LogicException('Must not read resources.'); });
    }
}
