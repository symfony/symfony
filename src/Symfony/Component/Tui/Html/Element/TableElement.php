<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html\Element;

use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Table elements, laid out by the default stylesheet (display: table...):
 * the columns are sized across the rows; colspan spans columns, rowspan
 * draws the cell across the rows below, keeping its column taken in them;
 * <col> and <colgroup> give the columns they span a least width.
 *
 * @experimental
 */
final class TableElement implements ElementFactoryInterface
{
    public function __construct(
        private readonly ContainerElement $container = new ContainerElement(),
    ) {
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $widget = $this->container->create($element, $builder);

        // A column: how many it spans, and its width attribute in CSS pixels
        if (\in_array(strtolower($element->localName), ['col', 'colgroup'], true)) {
            $span = $element->getAttribute('span');
            $style = is_numeric($span) && (int) $span > 1 ? (new Style())->withColumnSpan((int) $span) : new Style();
            if (preg_match('/^\s*(\d{1,5})(?:\.\d+)?\s*(px)?\s*$/i', $element->getAttribute('width') ?? '', $m)) {
                $style = $style->withMaxColumns(max(1, (int) round((int) $m[1] / 8)));
            } elseif (preg_match('/^\s*(\d{1,3})%\s*$/', $element->getAttribute('width') ?? '', $m)) {
                $style = $style->withWidthPercent((int) $m[1]);
            }
            if ($style != new Style()) {
                $widget->setStyle($style);
            }

            return $widget;
        }

        if (\in_array(strtolower($element->localName), ['td', 'th'], true)) {
            $columns = $element->getAttribute('colspan');
            $rows = $element->getAttribute('rowspan');
            $style = new Style();
            if (is_numeric($columns) && (int) $columns > 1) {
                $style = $style->withColumnSpan((int) $columns);
            }
            if (is_numeric($rows) && (int) $rows > 1) {
                $style = $style->withRowSpan((int) $rows);
            }
            if ($style != new Style()) {
                $widget->setStyle($style);
            }
        }

        return $widget;
    }
}
