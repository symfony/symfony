<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Render\Renderer as LegacyRenderer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\PercentLength;
use Symfony\Bridge\TuiHtml\Style\Position;
use Symfony\Bridge\TuiHtml\Style\PositionMode;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\StyleSheet;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadePositionPolicyTest extends TestCase
{
    public function testOwnSimplePositionResetsAuthoredInsetsAndRetainsDeviceCoordinates()
    {
        foreach ([new CellSize(8, 16), new CellSize(16, 32)] as $cell) {
            foreach ([new Position(PositionMode::Absolute, null, null, null, 1, 5, ['top' => 50]), new Position(PositionMode::Absolute, null, null, null, 1, 5, ['top' => -50]), new Position(PositionMode::Absolute, null, null, null, 1, 5, ['top' => new PercentLength(false, [[50, -2]])]), new Position(PositionMode::Absolute, null, null, null, 1, 5, ['top' => new PercentLength(false, [[-50, 2]])]), new Position(PositionMode::Absolute, 0, null, null, 1), new Position(PositionMode::Absolute, null, null, null, 1, 5, ['top' => 100]), new Position(PositionMode::Absolute, 0, null, null, 1, 5, ['top' => 100]), new Position(PositionMode::Absolute, null, null, null, null, 5, ['top' => new PercentLength(false, [[50, 1]]), 'left' => new PercentLength(false, [[50, -1]])])] as $position) {
                self::assertSame($this->paint(false, $cell, $position), $this->paint(true, $cell, $position));
            }
        }
    }

    public function testStyleProviderOrderUsesExistingNativeResolution()
    {
        $theme = new StyleSheet([RichTextWidget::class => new Style()->withPosition(new Position(PositionMode::Absolute, 0, null, null, 1))]);
        foreach ([false, true] as $before) {
            self::assertSame($this->paint(false, new CellSize(8, 16), null, $theme, $before), $this->paint(true, new CellSize(8, 16), null, $theme, $before));
        }
    }

    public function testComplexNativePositionsAreExplicitlyUnsupported()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Complex native positioned content');
        $this->paint(true, new CellSize(8, 16), new Position(PositionMode::Absolute)->withTranslate([50, 0]));
    }

    public function testNativePercentageInsetsRequireTheIntegerContractUsedByPosition()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Native percentage insets require integer values.');
        $this->paint(true, new CellSize(8, 16), new Position(PositionMode::Absolute, null, null, null, null, 0, ['top' => 50.5]));
    }

    private function paint(bool $core, CellSize $cell, ?Position $own = null, ?StyleSheet $theme = null, bool $before = false): array
    {
        $view = new HtmlBuilder()->useHtmlRenderer($core)->build('<div style="position:relative;width:12ch;height:3lh"><p id="p" style="margin:0;width:3ch;position:absolute;left:7ch;top:1lh">ABC</p></div><p style="margin:0">after</p>');
        $widget = $view->getById('p');
        if (null !== $own) {
            StyleBridge::set($widget, new Style()->withPosition($own));
        }
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = $core ? new FacadeRenderer(new Renderer()) : new LegacyRenderer();
        if ($before && null !== $theme) {
            $renderer->addStyleSheet($theme);
        }
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        if (!$before && null !== $theme) {
            $renderer->addStyleSheet($theme);
        }
        $renderer->setMediaContext(new MediaContext(20, 6, ColorScheme::Dark, Density::Regular, false, false, false, $cell));
        $lines = $renderer->renderFrame($outer, 20, 6)->toArray();
        $rect = $renderer->getWidgetRect($widget);
        self::assertNotNull($rect);
        if ($core && $rect->row >= 0 && $rect->col >= 0) {
            $snapshot = $view->getStyleSheet()->getMirrorProjection();
            $node = $snapshot['nodes'][$widget];
            self::assertSame($node, $view->getRoot()->getPresentation()->getResult()->hitTest(($rect->col + 0.5) * $cell->width, ($rect->row + 0.5) * $cell->height));
        }

        return [TerminalCellSnapshot::normalize(implode("\r\n", $lines), 20, 6, [229, 229, 229], [0, 0, 0]), $rect->row, $rect->col];
    }
}
