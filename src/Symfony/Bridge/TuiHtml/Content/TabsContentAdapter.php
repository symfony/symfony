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

use Symfony\Bridge\TuiHtml\Internal\ContextBridge;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Html\EmbeddedDocuments;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\TabsWidget;

/** @internal Native tab chrome around an active body laid out by the HTML engine. */
final class TabsContentAdapter implements ReplacedContentInterface
{
    private ?NativeContentAdapter $native = null;
    private ?NativeContentAdapter $natural = null;
    private ?int $preparedRevision = null;
    private array $bodies = [];
    private \WeakMap $projections;
    private readonly \WeakReference $widget;
    private readonly int $frameRevision;

    public function __construct(TabsWidget $widget, private readonly array $style, private readonly TerminalTextMeasurer $metrics, private readonly FontRegistry $fonts, private readonly MediaContext $media, private ?\Closure $renderBody)
    {
        $this->widget = \WeakReference::create($widget);
        $this->frameRevision = $widget->getRenderRevision();
        $this->projections = new \WeakMap();
    }

    public function getIntrinsicSize(): IntrinsicSize
    {
        $this->prepare(4096, 1000);

        return $this->natural()->getIntrinsicSize();
    }

    public function layout(float $width, float $availableHeight): ContentLayout
    {
        $columns = max(1, min(4096, (int) floor($width / $this->metrics->cellWidth)));
        $rows = max(1, min(100000, (int) floor($availableHeight / $this->metrics->cellHeight)));
        $layout = $this->prepare($columns, $rows)->layout($width, $availableHeight);
        $natural = $this->natural()->layout($width, $availableHeight);
        $body = $this->bodies[$key = $columns.':'.$rows] ?? null;
        if (null !== $body && isset($body['result'])) {
            $display = $layout->displayList->getOperations();
            $origin = new Rect($body['column'] * $this->metrics->cellWidth, $body['row'] * $this->metrics->cellHeight, $body['columns'] * $this->metrics->cellWidth, $body['rows'] * $this->metrics->cellHeight);
            array_push($display, ...EmbeddedDocuments::place($body['result']->getDisplayList(), $origin, false));
            $layout = new ContentLayout($natural->height, new DisplayList($display), false);
            $body['rows'] = max($body['rows'], \count($body['lines']));
        } elseif ($layout->height !== $natural->height) {
            $layout = new ContentLayout($natural->height, $layout->displayList);
        }
        if (null !== $body) {
            $this->projections[$layout] = $body;
        }

        return $layout;
    }

    public function getProjection(ContentLayout $layout): ?array
    {
        return $this->projections[$layout] ?? null;
    }

    public function dispose(): void
    {
        $this->native = null;
        $this->natural = null;
        $this->bodies = [];
        $this->projections = new \WeakMap();
        $this->renderBody = null;
    }

    private function prepare(int $columns, int $rows): NativeContentAdapter
    {
        $widget = $this->widget->get() ?? throw new \LogicException('The native tab widget is no longer registered.');
        if (null === $this->renderBody || $widget->getRenderRevision() !== ($this->preparedRevision ?? $this->frameRevision)) {
            throw new \LogicException('Native tabs changed after preparation; prepare and render the document again.');
        }
        $key = $columns.':'.$rows;
        if (!isset($this->bodies[$key])) {
            // Establish child document scopes at their actual viewport before the guarded native composition.
            $this->compose($widget, new \Symfony\Component\Tui\Render\RenderContext($columns, $rows));
        }
        if (null === $this->native || $widget->getRenderRevision() !== $this->preparedRevision) {
            $this->preparedRevision = $widget->getRenderRevision();
            $this->natural = null;
            $this->bodies = isset($this->bodies[$key]) ? [$key => $this->bodies[$key]] : [];
            $this->native = new NativeContentAdapter($this->widget, $this->preparedRevision, $this->style, $this->metrics, $this->fonts, $this->media, $this->compose(...), 0.0);
        }

        return $this->native;
    }

    private function natural(): NativeContentAdapter
    {
        return $this->natural ??= new NativeContentAdapter($this->widget, $this->preparedRevision, $this->style, $this->metrics, $this->fonts, $this->media, fn (TabsWidget $source, $context): array => $this->compose($source, $context, true), 0.0);
    }

    private function compose(TabsWidget $source, $context, bool $natural = false): array
    {
        $key = $context->getColumns().':'.$context->getRows();

        return $source->renderWithContent(ContextBridge::native($context), function ($body, $inner, int $row, int $column) use ($key, $natural): array {
            if (!isset($this->bodies[$key])) {
                if (\count($this->bodies) >= 8) {
                    $this->bodies = [];
                }
                $projection = ($this->renderBody)($body, $inner);
                $this->bodies[$key] = $projection + ['row' => $row, 'column' => $column, 'columns' => $inner->getColumns(), 'rows' => $inner->getRows()];
            }

            $body = $this->bodies[$key];

            if (isset($body['result'], $body['natural-rows'])) {
                return $natural ? \array_slice($body['lines'], 0, $body['natural-rows']) : array_fill(0, $body['natural-rows'], '');
            }

            return $body['lines'];
        });
    }
}
