<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Content;

use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Internal\ContextBridge;
use Symfony\Bridge\TuiHtml\Internal\FontFamilies;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Widget\ImageWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\SvgWidget;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

/** @internal */
final class NativeContentAdapter implements ReplacedContentInterface
{
    private array $renders = [];
    private ?IntrinsicSize $intrinsic = null;

    public function __construct(private readonly \WeakReference $widget, private readonly int $revision, private readonly array $computedStyle, private readonly TerminalTextMeasurer $metrics, private readonly FontRegistry $fonts, private readonly MediaContext $media, private readonly ?\Closure $renderPart = null, private readonly ?float $minimumWidth = null)
    {
    }

    public function getIntrinsicSize(): IntrinsicSize
    {
        if (null !== $this->intrinsic) {
            $this->widget();

            return $this->intrinsic;
        }
        $widget = $this->widget();
        if ($widget instanceof ImageWidget || $widget instanceof SvgWidget) {
            $widget->setBox(null, null, null);
            if (null !== $size = $widget->intrinsicCellSize()) {
                $this->widget();
                $width = $size[0] * $this->metrics->cellWidth;

                return $this->intrinsic = new IntrinsicSize($this->minimumWidth ?? $width, $width, $size[1] * $this->metrics->cellHeight, $widget->intrinsicAspectRatio());
            }
        }
        $columns = $widget instanceof InputWidget ? ($widget->isContentSized() ? $widget->getContentColumns() : ($widget->getPreferredColumns() ?? 4096)) : 4096;
        $lines = $this->render(max(1, $columns), 1000);
        $width = max([0, ...array_map(AnsiUtils::visibleWidth(...), $lines)]) * $this->metrics->cellWidth;

        // Unknown native widgets have no min-content API; preserve the legacy wide-render fallback.
        $ratio = null;
        if ($widget instanceof ImageWidget || $widget instanceof SvgWidget) {
            $ratio = $widget->intrinsicAspectRatio();
            $this->renders = [];
        }

        return $this->intrinsic = new IntrinsicSize($this->minimumWidth ?? $width, $width, \count($lines) * $this->metrics->cellHeight, $ratio);
    }

    public function layout(float $width, float $availableHeight): ContentLayout
    {
        $columns = max(1, min(4096, (int) floor($width / $this->metrics->cellWidth)));
        $rows = max(1, min(100000, (int) floor($availableHeight / $this->metrics->cellHeight)));
        $widget = $this->widget();
        if ($widget instanceof ImageWidget || $widget instanceof SvgWidget) {
            $widget->setBox($columns, $rows, 'fill');
        }
        $lines = $this->render($columns, $rows);
        $foreground = Value::color($this->computedStyle['color'] ?? '#eeeeee', $this->media) ?: Color::hex('#eeeeee');
        $background = Value::color($this->computedStyle['background-color'] ?? '#000000', $this->media) ?: Color::hex('#000000');
        $height = \count($lines) * $this->metrics->cellHeight;

        return new ContentLayout($height, new CellDisplayList($this->metrics)->convert($lines, $columns, max(1, \count($lines)), $foreground, $background));
    }

    private function render(int $columns, int $rows): array
    {
        $widget = $this->widget();
        $key = $columns.':'.$rows;
        if (isset($this->renders[$key])) {
            return $this->renders[$key];
        }
        $color = Value::color($this->computedStyle['color'] ?? '#eeeeee', $this->media) ?: Color::hex('#eeeeee');
        $style = new Style()->withColor($color);
        if (($this->computedStyle['font-weight'] ?? 400) >= 600) {
            $style = $style->withBold();
        }
        if (\in_array($this->computedStyle['font-style'] ?? '', ['italic', 'oblique'], true)) {
            $style = $style->withItalic();
        }
        if (null !== $font = FontFamilies::first($this->fonts, $this->computedStyle['font-family'] ?? 'monospace')) {
            $style = $style->withFont($font);
        }
        $context = new RenderContext($columns, $rows, $style, $this->fonts);
        $context = ContextBridge::forWidget($widget, $context);
        $lines = null === $this->renderPart ? $widget->render($context) : ($this->renderPart)($widget, $context);
        $this->widget();
        if (\count($lines) > 100000 || array_sum(array_map('strlen', $lines)) > 8 * 1024 * 1024) {
            throw new \LengthException('Native content exceeds the rendering budget.');
        }
        if (\count($this->renders) >= 8) {
            $this->renders = [];
        }

        return $this->renders[$key] = $lines;
    }

    private function widget(): AbstractWidget
    {
        $widget = $this->widget->get();
        if (null === $widget || $widget->getRenderRevision() !== $this->revision) {
            throw new \LogicException('Native content changed after preparation; prepare and render the document again.');
        }

        return $widget;
    }
}
