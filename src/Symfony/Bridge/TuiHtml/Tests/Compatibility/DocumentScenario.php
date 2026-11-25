<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\WordListHyphenator;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Render\RenderContext;

final class DocumentScenario
{
    public static function render(\Dom\HTMLDocument $document, ResourceLoader $loader, int $columns, int $rows, array $options, string $foreground, ?\Closure $colorLookup = null): array
    {
        $dppx = max(1, (int) ($options['dppx'] ?? 1));
        $metrics = new TerminalTextMeasurer(8 * $dppx, 16 * $dppx);
        $media = new MediaContext($columns, $rows, ColorScheme::from($options['scheme'] ?? 'dark'), Density::from($options['density'] ?? 'regular'), isset($options['contrast']), isset($options['forced-colors']), isset($options['reduced-motion']), new CellSize(8 * $dppx, 16 * $dppx), isset($options['pointer']), (int) ($options['colors'] ?? MediaContext::TRUE_COLORS), isset($options['text-sizing']));
        $session = (new HtmlRenderer())->createSession($document, new RenderingContext($columns * $metrics->cellWidth, $rows * $metrics->cellHeight, $metrics, $loader, null, null, [], '', ['default' => $foreground], WordListHyphenator::forFixtures(), null, $colorLookup));
        $widget = (new DocumentWidget($session, $metrics))->setMediaContext($media)->setDensity($media->density);
        $context = new RenderContext($columns, $rows);
        $widget->render($context);
        $element = static fn (string $selector): \Dom\Element => $document->querySelector($selector) ?? throw new \LogicException(\sprintf('No "%s" in the native document.', $selector));
        if (isset($options['focus'])) {
            $session->focus($element($options['focus']));
        }
        if (isset($options['modal'])) {
            $session->showModal($element($options['modal']));
        }
        if (isset($options['target'])) {
            $session->navigate($options['target']);
        }
        if (isset($options['visited'])) {
            $session->setVisitedLinks(explode(',', $options['visited']));
        }
        if (isset($options['hover'])) {
            $hover = $element($options['hover']);
            $bounds = $session->render()->getBounds($hover)[0] ?? null;
            if (null === $bounds) {
                throw new \LogicException(\sprintf('No box for hover target "%s".', $options['hover']));
            }
            $session->pointer($bounds->x + $bounds->width / 2, $bounds->y + $bounds->height / 2);
        }
        foreach (isset($options['highlight']) ? explode(',', $options['highlight']) : [] as $highlight) {
            [$name, $text] = explode(':', $highlight, 2) + [1 => ''];
            $session->highlight($name, str_replace('_', ' ', $text));
        }
        $widget->render($context);
        foreach (['scroll' => true, 'scroll-x' => false] as $option => $vertical) {
            if (isset($options[$option])) {
                [$selector, $offset] = explode(':', $options[$option], 2) + [1 => '0'];
                $target = $element($selector);
                [$x, $y] = $session->getScroll($target);
                $session->scroll($target, $vertical ? $x : (int) $offset * $metrics->cellWidth, $vertical ? (int) $offset * $metrics->cellHeight : $y);
                $widget->render($context);
            }
        }
        foreach (array_filter(explode(',', $options['click'] ?? '')) as $cell) {
            [$row, $column] = array_map('intval', explode(':', $cell, 2) + [1 => '0']);
            $widget->handlePointer($row, $column, true);
            $widget->render($context);
        }
        foreach (array_filter(explode(',', $options['keys'] ?? '')) as $key) {
            if ('f6' === $key) {
                $session->focusNext();
            } else {
                $session->key(match ($key) {
                    'enter' => new KeyboardEvent('Enter'),
                    'down', 'alt+down' => new KeyboardEvent('ArrowDown', null, false, false, 'alt+down' === $key),
                    'up' => new KeyboardEvent('ArrowUp'),
                    'escape' => new KeyboardEvent('Escape'),
                    'tab' => new KeyboardEvent('Tab'),
                    'space' => new KeyboardEvent('Space', ' '),
                    default => 1 === mb_strlen($key) ? new KeyboardEvent($key, $key) : throw new \LogicException(\sprintf('Unknown key "%s".', $key)),
                });
            }
            $widget->render($context);
        }
        $unsupported = array_values(array_diff(array_keys($options), ['columns', 'rows', 'scheme', 'density', 'contrast', 'forced-colors', 'reduced-motion', 'dppx', 'pointer', 'colors', 'focus', 'modal', 'target', 'visited', 'hover', 'scroll', 'scroll-x', 'click', 'keys', 'highlight', 'text-sizing']));

        return [$session, $widget->render($context), $unsupported];
    }
}
