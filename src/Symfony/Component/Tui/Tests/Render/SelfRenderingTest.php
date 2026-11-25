<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\RenderException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelfRenderingInterface;
use Symfony\Component\Tui\Widget\TextWidget;

final class SelfRenderingTest extends TestCase
{
    public function testOnlyTheOptedInSubtreeOwnsItsRenderingAndChrome()
    {
        $renderer = new Renderer();
        $owned = new SelfRenderingContainer();
        $child = new TextWidget('CHILD MUST NOT RENDER');
        $owned->add($child);
        $owned->setStyle((new Style())->withBorder([1])->withPadding([1])->withColor('red'));
        $sibling = new TextWidget('Sibling');
        $root = new ContainerWidget();
        $root->add($owned)->add($sibling);
        $lines = $renderer->renderFrame($root, 20, 8)->toArray();
        self::assertSame(['OWN', 'Sibling'], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
        self::assertSame($renderer->resolveStyle($owned)->apply('OWN'), $lines[0]);
        self::assertSame([20, 8], $owned->dimensions[0]);
        self::assertSame(1, $owned->styles[0]->getBorder()->left);
        self::assertSame(1, $owned->styles[0]->getPadding()->left);
        self::assertNull($renderer->getWidgetRect($child));
        self::assertSame(0, $renderer->getWidgetRect($owned)->row);
        self::assertSame(1, $renderer->getWidgetRect($sibling)->row);
        self::assertSame(20, $renderer->getWidgetRect($owned)->columns);
        self::assertSame(2, $renderer->getWidgetRect($root)->rows);
    }

    public function testNormalHooksCacheAndStyleLimitsStillApply()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        $widget->suffix = '!';
        $widget->setStyle((new Style())->withMaxColumns(12));
        $context = new RenderContext(20, 5);
        self::assertSame(['OWN!'], $renderer->renderWidgetLines($widget, $context)->toArray());
        self::assertSame(['OWN!'], $renderer->renderWidgetLines($widget, $context)->toArray());
        self::assertSame(2, $widget->beforeCalls);
        self::assertSame(1, $widget->renderCalls);
        self::assertSame(1, $widget->postCalls);
        self::assertSame([12, 5], $widget->dimensions[0]);
        $widget->invalidate();
        $renderer->renderWidgetLines($widget, $context);
        self::assertSame(2, $widget->renderCalls);
        self::assertSame(2, $widget->postCalls);
    }

    public function testIntrinsicWidthUsesTheOwnedBoxAndDoesNotAddChromeOrMeasureChildren()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        $widget->suffix = '++';
        $widget->add(new TextWidget('A much wider child'));
        $widget->setStyle((new Style())->withBorder([1])->withPadding([1]));
        self::assertSame(5, $renderer->measureIntrinsicWidth($widget, 40, 6));
        self::assertSame([40, 6], $widget->dimensions[0]);
        self::assertSame(1, $widget->beforeCalls);
        self::assertSame(1, $widget->postCalls);
        self::assertNull($widget->getRenderCache(40, 6));
    }

    public function testIntrinsicProbePreservesTrackedRectsAndClearsItsCache()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        $sibling = new TextWidget('Sibling');
        $root = new ContainerWidget();
        $root->add($widget)->add($sibling);
        $renderer->renderFrame($root, 20, 6);
        $rect = $renderer->getWidgetRect($widget);
        $siblingRect = $renderer->getWidgetRect($sibling);
        $calls = $widget->renderCalls;
        self::assertSame(3, $renderer->measureIntrinsicWidth($widget, 40, 4));
        self::assertSame($rect, $renderer->getWidgetRect($widget));
        self::assertSame($siblingRect, $renderer->getWidgetRect($sibling));
        self::assertNull($widget->getRenderCache(40, 4));
        $root->invalidate();
        $renderer->renderFrame($root, 20, 6);
        self::assertGreaterThan($calls + 1, $widget->renderCalls);
        self::assertSame(1, $renderer->getWidgetRect($sibling)->row);
    }

    public function testFailedIntrinsicProbeRestoresTrackingAndLeavesNoProbeCache()
    {
        $renderer = new Renderer();
        $renderer->renderFrame(new ContainerWidget(), 20, 6);
        $widget = new SelfRenderingContainer();
        $widget->suffix = 'TOO LONG';
        try {
            $renderer->measureIntrinsicWidth($widget, 4, 6);
            self::fail('The intrinsic probe must validate owned output width.');
        } catch (RenderException) {
            self::assertNull($widget->getRenderCache(4, 6));
        }
        $child = new TextWidget('After');
        $container = new ContainerWidget();
        $container->add($child);
        $renderer->renderWidgetLines($container, new RenderContext(20, 6));
        self::assertNotNull($renderer->getWidgetRect($child));
        self::assertSame(0, $renderer->getWidgetRect($child)->row);
    }

    public function testAnOptedInAppRootRetainsItsRootRect()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        self::assertSame(['OWN'], $renderer->renderFrame($widget, 20, 6)->toArray());
        $rect = $renderer->getWidgetRect($widget);
        self::assertSame(0, $rect->row);
        self::assertSame(0, $rect->col);
        self::assertSame(20, $rect->columns);
        self::assertSame(1, $rect->rows);
    }

    public function testHiddenWidgetsStillSkipTheirRenderAndPostHooks()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        $widget->setStyle((new Style())->withHidden(true));
        self::assertSame([], $renderer->renderWidgetLines($widget, new RenderContext(20, 6))->toArray());
        self::assertSame(1, $widget->beforeCalls);
        self::assertSame(0, $widget->renderCalls);
        self::assertSame(0, $widget->postCalls);
    }

    public function testOutputWidthValidationStillApplies()
    {
        $renderer = new Renderer();
        $widget = new SelfRenderingContainer();
        $widget->suffix = 'TOO LONG';
        $this->expectException(RenderException::class);
        $renderer->renderWidgetLines($widget, new RenderContext(4, 6));
    }
}

class SelfRenderingContainer extends ContainerWidget implements SelfRenderingInterface
{
    public int $beforeCalls = 0;
    public int $renderCalls = 0;
    public int $postCalls = 0;
    public string $suffix = '';
    public array $dimensions = [];
    public array $styles = [];

    public function beforeRender(): void
    {
        ++$this->beforeCalls;
    }

    public function render(RenderContext $context): array
    {
        ++$this->renderCalls;
        $this->dimensions[] = [$context->getColumns(), $context->getRows()];
        $this->styles[] = $context->getStyle();

        return [$context->getStyle()->apply('OWN')];
    }

    public function postRender(array $lines, RenderContext $context): array
    {
        ++$this->postCalls;

        return array_map(fn (string $line): string => $line.$this->suffix, $lines);
    }
}
