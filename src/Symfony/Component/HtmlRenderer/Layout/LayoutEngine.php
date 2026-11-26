<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Css\ColorScheme;
use Symfony\Component\HtmlRenderer\Css\ColorValue;
use Symfony\Component\HtmlRenderer\Css\GradientParser;
use Symfony\Component\HtmlRenderer\Css\Length;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\Hyphenation\LanguageHyphenator;
use Symfony\Component\HtmlRenderer\Paint\BorderImageSource;
use Symfony\Component\HtmlRenderer\Paint\GradientGeometry;
use Symfony\Component\HtmlRenderer\Paint\OutlineGeometry;
use Symfony\Component\HtmlRenderer\Paint\Transform;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;
use Symfony\Component\HtmlRenderer\Text\CaretGeometry;
use Symfony\Component\HtmlRenderer\Text\Hyphenation;
use Symfony\Component\HtmlRenderer\Text\LineBreaks;
use Symfony\Component\HtmlRenderer\Text\TextRun;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

/**
 * @internal
 */
final class LayoutEngine
{
    private const MAX_COLLAPSED_POSITIONS = 5000;

    private \SplObjectStorage $tableStructures;
    private \SplObjectStorage $collapsedTables;
    private \SplObjectStorage $collapsedWidths;
    private array $hyphenators = [];
    private array $lengthCache = [];
    private array $textMeasurements = [];
    private \WeakMap $markerGutters;
    private \SplObjectStorage $markerNodes;
    private \WeakMap $scrollKeys;
    private \WeakMap $boxOrigins;
    private array $scrollbars = [];
    private array $requiredBars = [];
    private bool $hasPickers = false;
    private bool $hasAnchors = false;
    private int $remainingJustificationFragments = 100000;
    private Box $rootBox;
    private \WeakMap $marginCache;
    private float $viewportWidth = 0.0;
    private float $viewportHeight = 0.0;
    private \SplObjectStorage $positionedBoxes;
    private \WeakMap $anchorSources;
    private \SplObjectStorage $subgridTracks;

    public function __construct(private readonly TextMeasurerInterface $textMeasurer, private readonly ?LayoutMetricsInterface $layoutMetrics = null, private readonly array $colors = [], private readonly string $preferredColorScheme = 'light', private readonly ?HyphenatorInterface $hyphenator = null, private readonly ?\Closure $colorLookup = null, private readonly ?\Symfony\Component\HtmlRenderer\Resource\ResourceCollection $resources = null)
    {
    }

    public function layout(Box $box, float $width, float $height): Fragment
    {
        if (!is_finite($width) || !is_finite($height) || $width < 0 || $height < 0) {
            throw new \InvalidArgumentException('The viewport must have finite non-negative dimensions.');
        }

        $this->hyphenators = [];
        $this->lengthCache = [];
        $this->textMeasurements = [];
        $this->hasPickers = false;
        $this->hasAnchors = false;
        $this->remainingJustificationFragments = 100000;
        $this->rootBox = $box;
        $this->marginCache = new \WeakMap();
        $this->viewportWidth = $width;
        $this->viewportHeight = $height;

        $this->positionedBoxes = new \SplObjectStorage();
        $this->scrollKeys = new \WeakMap();
        $this->boxOrigins = new \WeakMap();
        $this->scrollbars = $this->requiredBars = [];
        $this->markerGutters = new \WeakMap();
        $this->markerNodes = new \SplObjectStorage();
        if (null !== $this->layoutMetrics) {
            $this->prepareMarkerGutters($box);
        }

        if (null !== $this->layoutMetrics) {
            $this->scanScrollbars($box, []);
        }
        $state = new ScrollbarState();
        while (true) {
            $state->begin(\count($this->scrollbars));
            $this->marginCache = new \WeakMap();
            $this->positionedBoxes = new \SplObjectStorage();
            $this->anchorSources = new \WeakMap();
            $this->subgridTracks = new \SplObjectStorage();
            $this->tableStructures = new \SplObjectStorage();
            $this->collapsedTables = new \SplObjectStorage();
            $this->collapsedWidths = new \SplObjectStorage();
            $this->requiredBars = [];
            foreach ($this->scrollbars as $key => $policy) {
                $this->requiredBars[$key] = ['scroll' === $policy['overflow-x'] && $policy['horizontal'] > 0, 'scroll' === $policy['overflow-y'] && $policy['vertical'] > 0];
            }
            $root = $this->position($this->place($box, 0.0, 0.0, $width, $height, []), new Rect(0.0, 0.0, $width, $height));
            if ($state->settle($this->scrollbars, $this->requiredBars)) {
                return $this->hasPickers || $this->hasAnchors ? $this->anchorLayers($root) : $root;
            }
        }
    }

    private function place(Box $box, float $x, float $y, float $availableWidth, float $availableHeight, array $inherited, ?float $forcedWidth = null, array $floats = [], ?float $forcedHeight = null, ?float $containingWidth = null): Fragment
    {
        $style = array_replace(array_intersect_key($inherited, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing', 'line-height', 'white-space', 'visibility', 'text-decoration', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness', '-webkit-text-fill-color', 'text-align', 'text-align-last', 'text-justify', 'word-break', 'overflow-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', 'direction', '-renderer-line-context'])), $box->style);
        $style['display'] = $box->display;
        unset($style['-renderer-inline-decoration']);
        if ('inline' === $box->display && null === $box->image && null === $box->replacedContent
            && !(null === $box->pseudoElement && $box->node instanceof \Dom\Element && \in_array($box->node->localName, ['input', 'textarea', 'select', 'button'], true))
            && !\in_array($inherited['display'] ?? 'block', ['flex', 'inline-flex', 'grid', 'inline-grid'], true)
            && !\in_array($style['position'] ?? 'static', ['absolute', 'fixed'], true) && 'none' === ($style['float'] ?? 'none')
        ) {
            $style['-renderer-inline-decoration'] = '1';
        }
        $style = $this->tableOverflow($box, $style);
        unset($style['-renderer-scrollbar-key']);
        $scrollbarKey = $this->scrollbarKey($box, $style);
        if (null !== $scrollbarKey) {
            $style['-renderer-scrollbar-key'] = (string) $scrollbarKey;
        }
        if (isset($style['-renderer-first-line-root'])) {
            $style['-renderer-line-context'] = '0';
        }
        $this->hasAnchors = $this->hasAnchors || null !== $box->anchor;
        $font = $this->font($style, $inherited);
        $lengthWidth = $containingWidth ?? $availableWidth;
        $style['font-size'] = $font->size.'px';
        $style['line-height'] = $font->lineHeight.'px';
        $style['font-style'] = $font->style;
        foreach (['letter-spacing' => $font->letterSpacing, 'word-spacing' => $font->wordSpacing] as $property => $spacing) {
            if (isset($style[$property]) || 0.0 !== $spacing) {
                $style[$property] = $spacing.'px';
            }
        }
        if (!\in_array($style['text-decoration-thickness'] ?? 'auto', ['auto', 'from-font'], true)) {
            $thickness = $this->length($style['text-decoration-thickness'], $font->size, $font, 'text-decoration-thickness', true, false);
            $style['text-decoration-thickness'] = null === $thickness ? 'auto' : max(0.0, $thickness).'px';
        }
        if (null === $box->replacedContent && \in_array($box->display, ['table', 'inline-table'], true) && array_any($box->children, fn (Box $child): bool => $this->isCaption($child))) {
            return $this->captionedTable($box, $style, $x, $y, $availableWidth, $availableHeight, $inherited, $forcedWidth, $forcedHeight, $lengthWidth);
        }
        $origin = $this->boxOrigins[$box] ?? $box;
        if (\in_array($box->display, ['table', 'inline-table'], true) && 'collapse' === ($style['border-collapse'] ?? 'separate')) {
            $this->collapsedTable($box, $style, $lengthWidth, $font);
        }
        if (isset($this->collapsedWidths[$origin])) {
            $style['-renderer-collapsed-border'] = '1';
        }
        $marginInfo = $this->collapsedMargins($box, $lengthWidth, $font, 0, \in_array($inherited['display'] ?? '', ['flex', 'inline-flex', 'grid', 'inline-grid'], true), $forcedWidth);
        $margin = $marginInfo['margin'];
        $padding = $this->padding($box, $style, $lengthWidth, $font);
        $borders = $this->collapsedWidths[$origin] ?? $this->borders($style, $lengthWidth, $font);
        foreach (['top', 'right', 'bottom', 'left'] as $index => $edge) {
            $style['border-'.$edge.'-width'] = $borders[$index].'px';
            $style['padding-'.$edge] = $padding[$index].'px';
        }
        $x += $margin[3];
        $y += $margin[0];
        $boxHorizontal = $padding[1] + $padding[3] + $borders[1] + $borders[3];
        $boxVertical = $padding[0] + $padding[2] + $borders[0] + $borders[2];
        $reserved = $this->scrollbarInsets($scrollbarKey);
        $horizontal = $boxHorizontal + $reserved[1] + $reserved[3];
        $vertical = $boxVertical + $reserved[0] + $reserved[2];
        $naturalWidth = null;
        if (null !== $box->replacedContent && 'auto' === strtolower(trim($style['width'] ?? 'auto')) && null === $forcedWidth) {
            $naturalWidth = null !== $box->replacedContent->getIntrinsicSize()->aspectRatio ? $this->imageIntrinsicWidth($box, $font, $box->replacedContent, $availableWidth, $availableHeight) : $box->replacedContent->getIntrinsicSize()->preferredWidth;
        } elseif (null !== $box->image && 'auto' === strtolower(trim($style['width'] ?? 'auto')) && null === $forcedWidth) {
            $naturalWidth = $this->imageIntrinsicWidth($box, $font, $box->image, $availableWidth, $availableHeight) + ('border-box' === ($style['box-sizing'] ?? '') ? $boxHorizontal : 0.0);
        } elseif (\in_array($box->display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby', 'ruby-pair'], true) && !isset($style['width']) && null === $forcedWidth) {
            $availableWidth = max($this->minimumWidth($box, $font) + $horizontal + $margin[1] + $margin[3], min($availableWidth, $this->intrinsicWidth($box, $font) + $horizontal + $margin[1] + $margin[3]));
        }
        $intrinsic = null === $forcedWidth ? $this->intrinsicKeywordWidth($box, $font, $style['width'] ?? 'auto', max(0.0, $availableWidth - $margin[1] - $margin[3] - $horizontal), $lengthWidth, $horizontal) : null;
        if (null !== $intrinsic) {
            $intrinsic = $this->layoutMetrics?->constrainLength($intrinsic + $horizontal, 'width', false) ?? $intrinsic + $horizontal;
        }
        $specified = $forcedWidth ?? $naturalWidth ?? $intrinsic ?? $this->length($style['width'] ?? 'auto', $lengthWidth, $font, 'width');
        $naturalReplacedWidth = null !== $box->replacedContent && null !== $naturalWidth;
        $includesChrome = null !== $forcedWidth || null !== $intrinsic || 'border-box' === ($style['box-sizing'] ?? '') && !$naturalReplacedWidth;
        $width = null === $specified ? max(0.0, $availableWidth - $margin[1] - $margin[3]) : max(0.0, $specified + ($includesChrome ? 0.0 : $boxHorizontal));
        $width = $this->constrain($width, $style, 'width', $lengthWidth, $font, $boxHorizontal);
        $gutterWidth = $reserved[1] + $reserved[3];
        if ($gutterWidth > max(0.0, $width - $boxHorizontal)) {
            $ratio = max(0.0, $width - $boxHorizontal) / $gutterWidth;
            $reserved[1] *= $ratio;
            $reserved[3] *= $ratio;
            $horizontal = $boxHorizontal + $reserved[1] + $reserved[3];
        }
        $innerWidth = max(0.0, $width - $horizontal);
        $innerX = $x + $padding[3] + $borders[3] + $reserved[3];
        $innerY = $y + $padding[0] + $borders[0] + $reserved[0];
        $heightBasis = $forcedHeight ?? $this->length($style['height'] ?? 'auto', $availableHeight, $font, 'height', true);
        $usedContentHeight = null === $heightBasis ? null : max(0.0, $heightBasis - (null !== $forcedHeight || 'border-box' === ($style['box-sizing'] ?? '') ? $boxVertical : 0.0) - $reserved[0] - $reserved[2]);
        $childAvailableHeight = $usedContentHeight ?? $availableHeight;
        $children = [];
        $contentHeight = 0.0;
        $contentLayout = null;
        $surfaceBackground = null;
        $replacementIndex = null;
        $ratioContent = null !== $box->replacedContent?->getIntrinsicSize()->aspectRatio;
        if (null !== $box->image || $ratioContent) {
            $style['-renderer-replaced'] = '1';
        }
        if (null !== $box->replacedContent) {
            $specifiedContentHeight = $forcedHeight ?? $this->length($style['height'] ?? 'auto', $availableHeight, $font, 'height', true);
            $contentRoom = null === $specifiedContentHeight ? max(0.0, $availableHeight - $vertical) : max(0.0, $specifiedContentHeight - (null !== $forcedHeight || 'border-box' === ($style['box-sizing'] ?? '') ? $boxVertical : 0.0) - $reserved[0] - $reserved[2]);
            if (!$ratioContent && null === $specifiedContentHeight) {
                $contentRoom = max($contentRoom, $box->replacedContent->getIntrinsicSize()->height);
            }
            if (null !== $maximumHeight = $this->length($style['max-height'] ?? 'none', $availableHeight, $font, 'max-height', true)) {
                $contentRoom = min($contentRoom, max(0.0, $maximumHeight - ('border-box' === ($style['box-sizing'] ?? '') ? $boxVertical : 0.0) - $reserved[0] - $reserved[2]));
            }
            if ($ratioContent) {
                if (null === $specifiedContentHeight) {
                    $ratio = $this->imageRatio($box);
                    $intrinsic = $box->replacedContent->getIntrinsicSize();
                    $roundoff = 4 * \PHP_FLOAT_EPSILON * max(1.0, abs($innerWidth), abs($intrinsic->preferredWidth));
                    if (null === $ratio && 'auto' === strtolower(trim($style['width'] ?? 'auto')) && abs($innerWidth - $intrinsic->preferredWidth) <= $roundoff) {
                        $contentRoom = $intrinsic->height;
                    } else {
                        $contentRoom = null !== $ratio && 'border-box' === ($style['box-sizing'] ?? '') ? max(0.0, ($innerWidth + $boxHorizontal) / $ratio - $boxVertical) : $innerWidth / ($ratio ?? $intrinsic->aspectRatio);
                    }
                    $contentRoom = $this->layoutMetrics?->constrainLength($contentRoom, 'height', true) ?? $contentRoom;
                }
                $contentHeight = $contentRoom;
                $replacementIndex = \count($children);
                $children[] = new Fragment($box->node, new Rect($innerX, $innerY, $innerWidth, $contentHeight));
            } else {
                $contentLayout = $box->replacedContent->layout($innerWidth, $contentRoom);
                $surfaceBackground = $contentLayout->background;
                if (null !== $surfaceBackground) {
                    $contentLayout = new \Symfony\Component\HtmlRenderer\Content\ContentLayout($contentLayout->height, $contentLayout->displayList, $contentLayout->clipToBounds);
                }
                $contentHeight = $contentLayout->height;
                $children[] = new Fragment($box->node, new Rect($innerX, $innerY, $innerWidth, $contentHeight), array_intersect_key($style, array_flip(['color', 'visibility', 'pointer-events'])), null, [], null, null, [], null, null, null, null, null, null, $contentLayout);
            }
        }
        if (null !== $box->image) {
            $imageHeight = $this->length($style['height'] ?? 'auto', $availableHeight, $font, 'height', true);
            $ratio = $this->imageRatio($box);
            $borderBox = 'border-box' === ($style['box-sizing'] ?? '');
            $contentHeight = null !== $imageHeight ? max(0.0, $imageHeight - ($borderBox ? $boxVertical : 0.0)) : (null !== $ratio && $borderBox ? max(0.0, ($innerWidth + $boxHorizontal) / $ratio - $boxVertical) : $innerWidth / ($ratio ?? $box->image->width / $box->image->height));
            if (null === $imageHeight) {
                $contentHeight = $this->layoutMetrics?->constrainLength($contentHeight, 'height', true) ?? $contentHeight;
            }
            $replacementIndex = \count($children);
            $children[] = new Fragment($box->node, new Rect($innerX, $innerY, $innerWidth, max(0.0, $contentHeight)), array_intersect_key($style, array_flip(['color', 'image-rendering'])), null, [], $box->image);
        }
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        if (null !== $text) {
            $lineWidth = static fn (int $index): float => FlowGeometry::interval($floats, $innerX, $innerY + $index * $font->lineHeight, $innerWidth, $font->lineHeight)[1];
            $prepared = $this->hyphenate($text, $box, $style);
            if ('1' === ($style['-renderer-collapse-leading-space'] ?? null)) {
                $prepared = ltrim($prepared, 'pre-line' === ($style['white-space'] ?? 'normal') ? " \t\r\f" : " \t\r\n\f");
            }
            $hasRun = null !== $box->selection || null === $box->pseudoElement && $box->node instanceof \Dom\Element && ('textarea' === $box->node->localName || 'input' === $box->node->localName && !\in_array(strtolower($box->node->getAttribute('type') ?? 'text'), ['checkbox', 'radio', 'range', 'color', 'file', 'hidden', 'button', 'submit', 'reset', 'image'], true));
            $sourceLines = $hasRun && ($prepared !== $text || str_contains($text, "\u{00AD}")) ? [] : null;
            $lines = $this->lines($prepared, $innerWidth, $font, $style['white-space'] ?? 'normal', $lineWidth, $style['word-break'] ?? 'normal', $this->hyphenCharacter($style), $sourceLines, 'anywhere' === ($style['overflow-wrap'] ?? ''));
            $sourceGraphemes = null;
            $mappedCursor = 0;
            $sourceByte = $sourceOffset = 0;
            foreach ($lines as $lineIndex => $line) {
                if (isset($style['-renderer-line-context']) && ('1' === $style['-renderer-line-context'] || $lineIndex > 0)) {
                    $style['-renderer-line-crossed'] = '1';
                }
                $run = null;
                if ($hasRun) {
                    if (null !== $sourceLines) {
                        if (null === $sourceGraphemes) {
                            preg_match_all('/\X/u', $text, $sourceMatches);
                            $sourceGraphemes = $sourceMatches[0];
                        }
                        $run = TextRun::fromPreparedLine($sourceGraphemes, $sourceLines[$lineIndex], $line, $mappedCursor, $box->selection, $lineIndex > 0);
                    } else {
                        if ($lineIndex > 0 && isset($text[$sourceByte]) && \in_array($text[$sourceByte], ["\r", "\n"], true)) {
                            $sourceByte += "\r\n" === substr($text, $sourceByte, 2) ? 2 : 1;
                            ++$sourceOffset;
                        }
                        $found = '' === $line ? $sourceByte : strpos($text, $line, $sourceByte);
                        $found = false === $found ? $sourceByte : $found;
                        $start = $sourceOffset + (preg_match_all('/\X/u', substr($text, $sourceByte, $found - $sourceByte)) ?: 0);
                        $end = $start + (preg_match_all('/\X/u', $line) ?: 0);
                        $run = new TextRun($start, $end, $box->selection);
                        $sourceOffset = $end;
                        $sourceByte = $found + \strlen($line);
                    }
                }
                $metrics = $this->measureText($line, $font);
                $lineStyle = array_intersect_key($style, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing', 'line-height', 'white-space', 'text-align', 'text-align-last', 'text-justify', 'word-break', 'overflow-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', 'text-decoration', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness', '-webkit-text-fill-color', 'direction', 'visibility', 'caret-color', 'color-scheme']));
                if (isset($style['-renderer-first-line-origin']) && 0 === (int) ($style['-renderer-line-context'] ?? 0) && 0 === $lineIndex) {
                    foreach (['color', '-webkit-text-fill-color', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness'] as $property) {
                        if (isset($style['-renderer-first-line-'.$property])) {
                            $value = $style['-renderer-first-line-'.$property];
                            if ('text-decoration-line' === $property) {
                                $decorations = array_unique(array_filter(explode(' ', ($lineStyle[$property] ?? 'none').' '.$value), static fn (string $line): bool => 'none' !== $line && '' !== $line));
                                $value = implode(' ', $decorations) ?: 'none';
                            }
                            $lineStyle[$property] = $value;
                        }
                    }
                }
                $lineStyle['-renderer-baseline'] = (string) (($font->lineHeight - $metrics->ascent - $metrics->descent) / 2 + $metrics->ascent);
                [$lineX, $lineRoom] = FlowGeometry::interval($floats, $innerX, $innerY + $contentHeight, $innerWidth, $font->lineHeight);
                $alignment = $style['text-align'] ?? 'start';
                if ($lineIndex === \count($lines) - 1) {
                    $alignment = $style['text-align-last'] ?? ('justify' === $alignment ? 'start' : $alignment);
                }
                $lineX += InlineAlignment::offset($lineRoom, $metrics->width, $alignment, $style['direction'] ?? 'ltr');
                $mode = $style['text-justify'] ?? 'auto';
                $interCharacter = 'inter-character' === $mode || 'auto' === $mode && !str_contains(trim($line), ' ') && LineBreaks::isIdeographic($line);
                $words = [];
                if ('justify' === $alignment && 'none' !== $mode) {
                    if ($interCharacter) {
                        preg_match_all('/\X/u', $line, $segments);
                        $words = $segments[0];
                    } else {
                        $words = preg_split('/ +/', trim($line), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
                    }
                }
                if (\count($words) > 1 && $lineRoom > $metrics->width) {
                    $widths = array_map(fn (string $word): float => $this->measureText($word, $font)->width, $words);
                    $gap = ($lineRoom - array_sum($widths)) / (\count($words) - 1);
                    $gaps = $this->distribute(array_fill(0, \count($words) - 1, $gap), false);
                    $wordByte = 0;
                    foreach ($words as $index => $word) {
                        $wordRun = null;
                        if (null !== $run) {
                            $wordByte = strpos($line, $word, $wordByte);
                            $wordStart = preg_match_all('/\X/u', substr($line, 0, $wordByte)) ?: 0;
                            $wordRun = $run->slice($wordStart, preg_match_all('/\X/u', $word) ?: 0);
                            $wordByte += \strlen($word);
                        }
                        $children[] = new Fragment($box->node, new Rect($lineX, $innerY + $contentHeight, $widths[$index], $font->lineHeight), $lineStyle, $word, [], null, null, [], $wordRun, $box->pseudoElement);
                        $lineX += $widths[$index] + ($gaps[$index] ?? 0.0);
                    }
                } else {
                    $children[] = new Fragment($box->node, new Rect($lineX, $innerY + $contentHeight, $metrics->width, $font->lineHeight), $lineStyle, $line, [], null, null, [], $run, $box->pseudoElement);
                }
                $contentHeight += $font->lineHeight;
            }
        }
        if (null !== $box->selection) {
            $children = $this->caretAffinity($children);
        }
        if (isset($style['-renderer-line-context']) && isset($lines) && \count($lines) > 1) {
            $style['-renderer-line-context'] = '1';
            $style['-renderer-line-crossed'] = '1';
        }
        $visible = null !== $box->replacedContent ? [] : array_values(array_filter($box->children, static fn (Box $child): bool => 'none' !== $child->display));
        $positioned = array_values(array_filter($visible, static fn (Box $child): bool => null !== $child->topLayer || \in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)));
        $boxes = array_values(array_filter($visible, static fn (Box $child): bool => null === $child->topLayer && !\in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)));
        if (!\in_array($box->display, ['inline', 'ruby'], true)) {
            $boxes = InlineFormatting::collapseWhitespace($boxes, $this->copiedBox(...), $style['white-space'] ?? 'normal');
        }
        $gap = max(0.0, $this->length($style['gap'] ?? '0', $innerWidth, $font) ?? 0.0);
        if (null !== $contentLayout) {
            // The atomic content leaf participates in ordinary overflow and painting.
        } elseif ('ruby-pair' === $box->display) {
            [$children, $rubyWidth, $contentHeight, $baseline] = $this->rubyPair($boxes, $innerX, $innerY, $style);
            $width = $rubyWidth + $horizontal;
            $style['-renderer-ruby-baseline'] = (string) ($baseline + $padding[0] + $borders[0]);
        } elseif (\in_array($box->display, ['block', 'flow-root', 'list-item'], true) && null !== $columns = $this->columnSpecification($style, $innerWidth, $font)) {
            [$columnChildren, $contentHeight] = $this->columns($boxes, $innerX, $innerY, $innerWidth, $availableHeight, $style, $font, $vertical, $columns, $reserved[0] + $reserved[2]);
            array_push($children, ...$columnChildren);
        } elseif (\in_array($box->display, ['table', 'inline-table'], true)) {
            [$tableChildren, $contentHeight, $tableWidth] = $this->table($box, $innerX, $innerY, $innerWidth, $availableHeight, $style, $font);
            array_push($children, ...$tableChildren);
            $width = 'auto' === ($style['width'] ?? 'auto') && null === $forcedWidth ? $tableWidth + $horizontal : max($width, $tableWidth + $horizontal);
        } elseif (\in_array($box->display, ['flex', 'inline-flex'], true)) {
            [$flexChildren, $contentHeight] = $this->flex($boxes, $innerX, $innerY, $innerWidth, $childAvailableHeight, $style, $font, $vertical, $reserved[0] + $reserved[2], $usedContentHeight);
            array_push($children, ...$flexChildren);
        } elseif (\in_array($box->display, ['grid', 'inline-grid'], true)) {
            [$gridChildren, $contentHeight] = $this->grid($box, $boxes, $innerX, $innerY, $innerWidth, $childAvailableHeight, $style, $font, $vertical, $reserved[0] + $reserved[2], $usedContentHeight);
            array_push($children, ...$gridChildren);
        } else {
            $inlineX = 0.0;
            $lineHeight = 0.0;
            $flowLineWidth = $innerWidth;
            $activeFloats = $floats;
            $ownFloatBottom = $innerY;
            $lastFloatTop = $innerY;
            $inlineGroup = [];
            $inlineStart = $innerX;
            $inlineOffsets = [];
            $floatLineShift = 0.0;
            $floatLineMetrics = null;
            $floatLineFont = $floatLineStrut = null;
            $markerHeight = 0.0;
            $inlineParts = new \SplObjectStorage();
            $pendingMargin = null;
            $marginBase = 0.0;
            for ($childIndex = 0; $childIndex < \count($boxes); ++$childIndex) {
                $child = $boxes[$childIndex];
                if ('marker' === $child->pseudoElement && 'inside' !== ($child->style['list-style-position'] ?? $style['list-style-position'] ?? 'outside')) {
                    $markerWidth = $this->intrinsicWidth($child, $this->font($child->style, $style));
                    $markerX = 'rtl' === ($style['direction'] ?? 'ltr') ? $innerX + $innerWidth : $innerX - $markerWidth;
                    $marker = $this->place($child, $markerX, $innerY, $markerWidth, $availableHeight, $style, $markerWidth);
                    $children[] = $marker;
                    $markerHeight = max($markerHeight, $marker->bounds->height);
                    continue;
                }
                if ('line-break' === $child->display) {
                    $style['-renderer-line-crossed'] = '1';
                    $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, true, true, $inlineOffsets, $floatLineShift);
                    $contentHeight += max($font->lineHeight, $lineHeight);
                    $contentHeight = FlowGeometry::clear($activeFloats, $innerY + $contentHeight, $child->style['clear'] ?? 'none') - $innerY;
                    $pendingMargin = null;
                    $marginBase = $contentHeight;
                    $inlineX = $lineHeight = 0.0;
                    if (isset($style['-renderer-line-context'])) {
                        $style['-renderer-line-context'] = '1';
                    }
                    continue;
                }
                $clear = FlowGeometry::clear($activeFloats, $innerY + $contentHeight, $child->style['clear'] ?? 'none');
                if ($clear > $innerY + $contentHeight) {
                    $hadLine = [] !== $inlineGroup;
                    $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, true, false, $inlineOffsets, $floatLineShift);
                    $contentHeight = $clear - $innerY;
                    $pendingMargin = null;
                    $marginBase = $contentHeight;
                    $inlineX = $lineHeight = 0.0;
                    if ($hadLine && isset($style['-renderer-line-context'])) {
                        $style['-renderer-line-context'] = '1';
                    }
                }
                $float = $child->style['float'] ?? 'none';
                if (\in_array($float, ['left', 'right'], true)) {
                    $floatStyle = $child->style;
                    $floatFont = $this->font($floatStyle, $style);
                    $floatPadding = $this->padding($child, $floatStyle, $innerWidth, $floatFont);
                    $floatBorders = $this->borders($floatStyle, $innerWidth, $floatFont);
                    $floatWidth = !isset($floatStyle['width']) ? min($innerWidth, $this->intrinsicWidth($child, $floatFont) + $floatPadding[1] + $floatPadding[3] + $floatBorders[1] + $floatBorders[3]) : null;
                    $floatBox = $this->copiedBox($child, new Box($child->node, $child->display, $floatStyle, $child->children, $child->text, $child->image, $child->selection, $child->pseudoElement, $child->topLayer, $child->borderImage, $child->anchor, $child->replacedContent, $child->anchorReferences, $child->positionTries));
                    $fragment = $this->place($floatBox, 0.0, 0.0, $innerWidth, $availableHeight, $style, $floatWidth);
                    $margin = $this->edges($child->style, 'margin', $innerWidth, $this->font($floatStyle, $style));
                    $outerWidth = max(0.0, $fragment->bounds->width + $margin[1] + $margin[3]);
                    $top = max($innerY + $contentHeight, $lastFloatTop);
                    while (true) {
                        [$left, $room] = FlowGeometry::interval($activeFloats, $innerX, $top, $innerWidth, max($font->lineHeight, $fragment->bounds->height));
                        $next = FlowGeometry::nextEnd($activeFloats, $top);
                        if ($outerWidth <= $room || null === $next) {
                            break;
                        }
                        $top = $next;
                    }
                    $left = 'left' === $float ? $left : $left + $room - $outerWidth;
                    $fragment = $this->translate($fragment, $left, $top);
                    $children[] = $fragment;
                    $outer = new Rect($fragment->bounds->x - $margin[3], $fragment->bounds->y - $margin[0], $outerWidth, max(0.0, $fragment->bounds->height + $margin[0] + $margin[2]));
                    $gap = max(0.0, $this->layoutMetrics?->constrainLength(0.0, 'float-exclusion-gap', false, $floatFont) ?? 0.0);
                    $exclusion = $gap > 0 ? new Rect($outer->x - ('right' === $float ? $gap : 0.0), $outer->y, $outer->width + $gap, $outer->height) : $outer;
                    $activeFloats[] = ['side' => $float, 'bounds' => $exclusion];
                    $lastFloatTop = $top;
                    $ownFloatBottom = max($ownFloatBottom, $outer->y + $outer->height);
                    continue;
                }
                $inline = \in_array($child->display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby', 'ruby-pair'], true);
                $inlineFont = $this->font($child->style, $style);
                $inlineMargin = $inline ? $this->edges($child->style, 'margin', $innerWidth, $inlineFont) : [0.0, 0.0, 0.0, 0.0];
                $intrinsic = $inline ? $this->inlineOuterWidth($child, $innerWidth, $inlineFont) : 0.0;
                $lineStart = 0.0;
                $lineEnd = $innerWidth;
                $handlesFloatLine = $inline && $activeFloats && ($inlineGroup || $intrinsic <= $innerWidth || 'anywhere' === ($child->style['overflow-wrap'] ?? ''));
                if ($handlesFloatLine) {
                    while (true) {
                        [$left, $flowLineWidth] = FlowGeometry::interval($activeFloats, $innerX, $innerY + $contentHeight, $innerWidth, max($font->lineHeight, $inlineFont->lineHeight, $inlineGroup && null !== $floatLineMetrics ? $this->lineBoxHeight($floatLineMetrics) : 0.0));
                        $lineStart = $left - $innerX;
                        $lineEnd = $lineStart + $flowLineWidth;
                        $next = FlowGeometry::nextEnd($activeFloats, $innerY + $contentHeight, max($font->lineHeight, $inlineFont->lineHeight));
                        if ($inlineGroup || $flowLineWidth > 0 || null === $next) {
                            break;
                        }
                        $contentHeight = $next - $innerY;
                    }
                    if (!$inlineGroup) {
                        $inlineX = $lineStart;
                    }
                } elseif ($inline) {
                    $flowLineWidth = $innerWidth;
                }
                if ($inline && ('anywhere' === ($child->style['overflow-wrap'] ?? '') && $intrinsic > $lineEnd - $lineStart || $handlesFloatLine && $intrinsic <= $innerWidth && $intrinsic > $lineEnd - $inlineX || ($inlineX > $lineStart || '1' === ($child->style['-renderer-highlight'] ?? null)) && $intrinsic > $lineEnd - $inlineX || isset($boxes[$childIndex + 1]) && InlineFormatting::joins($child, $boxes[$childIndex + 1]) && $intrinsic + $this->inlineOuterWidth($boxes[$childIndex + 1], $innerWidth, $this->font($boxes[$childIndex + 1]->style, $style)) > $lineEnd - $inlineX) && null !== $parts = InlineFormatting::split($child)) {
                    if (\count($parts) > 1) {
                        foreach ($parts as $part) {
                            $inlineParts[$part] = true;
                        }
                        array_splice($boxes, $childIndex, 1, $parts);
                        $child = $boxes[$childIndex];
                        $intrinsic = $this->inlineOuterWidth($child, $innerWidth, $inlineFont);
                    }
                }
                $fragmentedInline = isset($inlineParts[$child]);
                if ($fragmentedInline) {
                    $trimmed = $this->trimBoundary($child, true);
                    $trimmedWidth = $this->inlineOuterWidth($trimmed, $innerWidth, $inlineFont);
                    if ($intrinsic > $lineEnd - $inlineX && $trimmedWidth <= $lineEnd - $inlineX) {
                        $child = $trimmed;
                        $intrinsic = $trimmedWidth;
                    }
                }
                $joinedPrevious = $childIndex > 0 && InlineFormatting::joins($boxes[$childIndex - 1], $child);
                $runWidth = $intrinsic;
                $runEnd = $childIndex + 1;
                if ($inline && !$joinedPrevious) {
                    for ($nextIndex = $childIndex + 1; isset($boxes[$nextIndex]) && InlineFormatting::joins($boxes[$nextIndex - 1], $boxes[$nextIndex]); ++$nextIndex) {
                        $nextBox = $boxes[$nextIndex];
                        if (null !== $nextParts = InlineFormatting::split($nextBox)) {
                            if (\count($nextParts) > 1) {
                                array_splice($boxes, $nextIndex, 1, $nextParts);
                                foreach ($nextParts as $part) {
                                    $inlineParts[$part] = true;
                                }
                                $nextBox = $boxes[$nextIndex];
                            }
                        }
                        $runEnd = $nextIndex + 1;
                        $runWidth += $this->inlineOuterWidth($this->trimBoundary($nextBox, true), $innerWidth, $this->font($nextBox->style, $style));
                    }
                }
                if ($inline && !$joinedPrevious && 'anywhere' === ($child->style['overflow-wrap'] ?? '') && $runWidth > $lineEnd - $lineStart) {
                    $emergencyParts = [];
                    for ($index = $childIndex; $index < $runEnd; ++$index) {
                        array_push($emergencyParts, ...(InlineFormatting::split($boxes[$index], true) ?? [$boxes[$index]]));
                    }
                    if (\count($emergencyParts) > $runEnd - $childIndex) {
                        foreach ($emergencyParts as $part) {
                            $inlineParts[$part] = true;
                        }
                        array_splice($boxes, $childIndex, $runEnd - $childIndex, $emergencyParts);
                        $child = $boxes[$childIndex];
                        $inlineFont = $this->font($child->style, $style);
                        $intrinsic = $runWidth = $this->inlineOuterWidth($child, $innerWidth, $inlineFont);
                        $fragmentedInline = true;
                    }
                }
                if ($handlesFloatLine && !$inlineGroup && 'anywhere' !== ($child->style['overflow-wrap'] ?? '') && $runWidth > $flowLineWidth) {
                    while (null !== $next = FlowGeometry::nextEnd($activeFloats, $innerY + $contentHeight, max($font->lineHeight, $inlineFont->lineHeight))) {
                        $contentHeight = $next - $innerY;
                        [$left, $flowLineWidth] = FlowGeometry::interval($activeFloats, $innerX, $next, $innerWidth, max($font->lineHeight, $inlineFont->lineHeight));
                        $lineStart = $left - $innerX;
                        $lineEnd = $lineStart + $flowLineWidth;
                        $inlineX = $lineStart;
                        if ($runWidth <= $flowLineWidth) {
                            break;
                        }
                    }
                }
                if ($inlineGroup && (!$inline || !$joinedPrevious && $runWidth > $lineEnd - $inlineX)) {
                    $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, true, !$inline, $inlineOffsets, $floatLineShift);
                    $contentHeight += $lineHeight;
                    $inlineX = $lineHeight = 0.0;
                    if ($handlesFloatLine) {
                        [$left, $flowLineWidth] = FlowGeometry::interval($activeFloats, $innerX, $innerY + $contentHeight, $innerWidth, max($font->lineHeight, $inlineFont->lineHeight, $inlineGroup && null !== $floatLineMetrics ? $this->lineBoxHeight($floatLineMetrics) : 0.0));
                        $lineStart = $left - $innerX;
                        $lineEnd = $lineStart + $flowLineWidth;
                        $inlineX = $lineStart;
                    }
                    if (isset($style['-renderer-line-context'])) {
                        $style['-renderer-line-context'] = '1';
                    }
                }
                if ($fragmentedInline && !$inlineGroup) {
                    $child = $this->trimBoundary($child, false);
                }
                $childX = $innerX + $inlineX;
                $childY = $innerY + $contentHeight;
                $blockMargin = null;
                if (!$inline && \in_array($child->display, ['block', 'flow-root', 'list-item'], true)) {
                    $blockMargin = $this->collapsedMargins($child, $innerWidth, $this->font($child->style, $style));
                    if (null === $pendingMargin) {
                        $marginBase = $contentHeight;
                    }
                    if (\in_array($child, $marginInfo['first'], true)) {
                        $pendingMargin = null;
                        $marginBase = $contentHeight = 0.0;
                        $childY = $innerY - $blockMargin['top']->value();
                    } elseif (null !== $pendingMargin) {
                        $merged = $pendingMargin->merge($blockMargin['top']);
                        $childY = $innerY + $marginBase + $merged->value() - $blockMargin['top']->value();
                    }
                } else {
                    $pendingMargin = null;
                }
                $childWidth = max(0.0, $lineEnd - $inlineX);
                $childFloats = $handlesFloatLine ? [] : $activeFloats;
                if (Containment::layout($child->style) || \in_array($child->display, ['flow-root', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'table', 'inline-table'], true) || \in_array($child->style['overflow-x'] ?? $child->style['overflow'] ?? 'visible', ['hidden', 'auto', 'scroll'], true) || \in_array($child->style['overflow-y'] ?? $child->style['overflow'] ?? 'visible', ['hidden', 'auto', 'scroll'], true)) {
                    $least = $this->length($child->style['width'] ?? 'auto', $childWidth, $this->font($child->style, $style));
                    while (true) {
                        [$left, $room] = FlowGeometry::interval($activeFloats, $childX, $childY, $childWidth, $font->lineHeight);
                        $next = FlowGeometry::nextEnd($activeFloats, $childY);
                        if (null === $least || $least <= $room || null === $next) {
                            break;
                        }
                        $childY = $next;
                    }
                    $childX = $left;
                    $childWidth = $room;
                    $childFloats = [];
                }
                $fragment = $this->place($child, $childX, $childY, $childWidth, $childAvailableHeight, $style, null, $childFloats, null, $innerWidth);
                $prospectiveLine = null;
                $atomic = null !== $child->image || null !== $child->replacedContent || \in_array($child->display, ['inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby-pair'], true);
                if ($inline && $activeFloats && ($handlesFloatLine || $atomic)) {
                    $floatLineFont ??= $this->font($style, []);
                    $floatLineStrut ??= $this->lineStrut($floatLineFont);
                    $baseMetrics = [$floatLineStrut[0], $floatLineStrut[1], 0.0];
                    $previousLine = $inlineGroup ? $floatLineMetrics : $baseMetrics;
                    if (null === $previousLine) {
                        // Floats can enter an already started line. Accumulate its earlier runs once.
                        $previousLine = $baseMetrics;
                        foreach ($inlineGroup as $index) {
                            $previousLine = self::extendLineMetrics($previousLine, $this->inlineVerticalMetrics($children[$index], $floatLineFont, $floatLineStrut[2], $style));
                        }
                    }
                    $item = $this->inlineVerticalMetrics($fragment, $floatLineFont, $floatLineStrut[2], $style);
                    $prospectiveLine = self::extendLineMetrics($previousLine, $item);
                    $outerWidth = $fragment->bounds->width + $inlineMargin[1] + $inlineMargin[3];
                    $outerHeight = max($this->lineBoxHeight($prospectiveLine), $atomic ? $fragment->bounds->height + $inlineMargin[0] + $inlineMargin[2] : 0.0);
                    $normalLeft = $inlineGroup ? $inlineStart : $childX;
                    $normalRight = max($innerX + $inlineX, $childX + $outerWidth);
                    [$left, $room] = FlowGeometry::interval($activeFloats, $innerX, $innerY + $contentHeight, $innerWidth, $outerHeight);
                    if ($normalLeft < $left || $normalRight > $left + $room) {
                        if ($normalRight - $normalLeft <= $room) {
                            // Keep a fitting line together. Older runs receive this origin change only at finalization.
                            $dx = $left - $normalLeft;
                            $floatLineShift += $dx;
                            $inlineX += $dx;
                            $childX += $dx;
                            $fragment = $this->translate($fragment, $dx, 0.0);
                            $inlineStart = $left;
                        } else {
                            if ($inlineGroup) {
                                $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, true, false, $inlineOffsets, $floatLineShift);
                                $contentHeight += $lineHeight;
                                $lineHeight = 0.0;
                            }
                            $prospectiveLine = self::extendLineMetrics($baseMetrics, $item);
                            $outerHeight = max($this->lineBoxHeight($prospectiveLine), $atomic ? $fragment->bounds->height + $inlineMargin[0] + $inlineMargin[2] : 0.0);
                            $top = $innerY + $contentHeight;
                            while (true) {
                                [$left, $room] = FlowGeometry::interval($activeFloats, $innerX, $top, $innerWidth, $outerHeight);
                                $next = FlowGeometry::nextEnd($activeFloats, $top, $outerHeight);
                                if ($outerWidth <= $room || null === $next) {
                                    break;
                                }
                                $top = $next;
                            }
                            $fragment = $this->translate($fragment, $left - $childX, $top - $childY);
                            $childX = $left;
                            $childY = $top;
                            $contentHeight = $top - $innerY;
                            $inlineX = $left - $innerX;
                        }
                        $flowLineWidth = $room;
                        $lineEnd = $left - $innerX + $room;
                    }
                }
                if ($inline) {
                    if (!$inlineGroup) {
                        $inlineStart = $childX;
                    }
                    if ($activeFloats) {
                        $inlineOffsets[\count($children)] = $floatLineShift;
                        $floatLineMetrics = $prospectiveLine;
                    }
                    if ('1' === ($fragment->style['-renderer-line-crossed'] ?? null)) {
                        $style['-renderer-line-context'] = '1';
                        $style['-renderer-line-crossed'] = '1';
                    }
                    $inlineGroup[] = \count($children);
                }
                $children[] = $fragment;
                $bottom = $fragment->bounds->y + $fragment->bounds->height - $innerY;
                if ('relative' === ($child->style['position'] ?? 'static')) {
                    $childFont = $this->font($child->style, $style);
                    $relativeY = $this->length($child->style['top'] ?? 'auto', $availableHeight, $childFont, 'top', true) ?? -($this->length($child->style['bottom'] ?? '0', $availableHeight, $childFont, 'bottom', true) ?? 0.0);
                    $bottom -= $relativeY;
                }
                if ($inline) {
                    $inlineX += $fragment->bounds->width + $inlineMargin[1] + $inlineMargin[3];
                    $lineHeight = max($lineHeight, $bottom - $contentHeight);
                    if ($inlineX >= $lineEnd && (!isset($boxes[$childIndex + 1]) || !InlineFormatting::joins($child, $boxes[$childIndex + 1]))) {
                        $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, isset($boxes[$childIndex + 1]) || !\in_array($box->display, ['inline', 'ruby'], true) || $box === $this->rootBox, false, $inlineOffsets, $floatLineShift);
                        $contentHeight += $lineHeight;
                        $inlineX = $lineHeight = 0.0;
                        if (isset($style['-renderer-line-context'])) {
                            $style['-renderer-line-context'] = '1';
                        }
                    }
                } else {
                    $childFont = $this->font($child->style, $style);
                    $childMargin = $blockMargin['margin'] ?? $this->edges($child->style, 'margin', $innerWidth, $childFont);
                    if (null !== $blockMargin) {
                        if ($blockMargin['through']) {
                            $pendingMargin = null === $pendingMargin ? $blockMargin['top'] : $pendingMargin->merge($blockMargin['top']);
                        } else {
                            $marginBase = $bottom;
                            $pendingMargin = $blockMargin['bottom'];
                        }
                        if (\in_array($child, $marginInfo['first'], true) && $blockMargin['through']) {
                            $pendingMargin = null;
                            $contentHeight = 0.0;
                        } else {
                            $contentHeight = $marginBase + (\in_array($child, $marginInfo['last'], true) ? 0.0 : $pendingMargin->value());
                        }
                    } else {
                        $pendingMargin = null;
                        $contentHeight = $bottom + $childMargin[2];
                    }
                }
            }
            // Inline descendants defer their final whitespace boundary to the containing line.
            $this->alignLine($children, $inlineGroup, $flowLineWidth, $style, $lineHeight, $innerY + $contentHeight, !\in_array($box->display, ['inline', 'ruby'], true) || $box === $this->rootBox, true, $inlineOffsets, $floatLineShift);
            $contentHeight = max($markerHeight, $contentHeight + $lineHeight);
            if ('flow-root' === $box->display || Containment::layout($style) || \in_array($style['overflow-x'] ?? $style['overflow'] ?? 'visible', ['hidden', 'auto', 'scroll'], true) || \in_array($style['overflow-y'] ?? $style['overflow'] ?? 'visible', ['hidden', 'auto', 'scroll'], true)) {
                $contentHeight = max($contentHeight, $ownFloatBottom - $innerY);
            }
        }
        if ($marginInfo['through']) {
            $contentHeight = 0.0;
        }
        $specifiedHeight = $forcedHeight ?? $this->length($style['height'] ?? 'auto', $availableHeight, $font, 'height', true);
        if (null === $specifiedHeight && null !== $containedHeight = $this->containedSize($style, true, $availableHeight, $font)) {
            $contentHeight = $containedHeight;
        }
        $height = null === $specifiedHeight ? $contentHeight + $vertical : max(0.0, $specifiedHeight + (null !== $forcedHeight || 'border-box' === ($style['box-sizing'] ?? '') ? 0.0 : $boxVertical));
        $height = $this->constrain($height, $style, 'height', $availableHeight, $font, $boxVertical);
        if (null === $contentLayout && \in_array($box->display, ['table', 'inline-table'], true)) {
            $height = max($height, $contentHeight + $vertical);
        }

        if (null !== $replacementIndex) {
            $target = new Rect($innerX, $innerY, $innerWidth, max(0.0, $height - $vertical));
            $intrinsic = $box->replacedContent?->getIntrinsicSize();
            $naturalWidth = null !== $intrinsic ? $intrinsic->preferredWidth : ($this->length($box->image->width.'px', $availableWidth, $font, 'width') ?? 0.0);
            $naturalHeight = null !== $intrinsic ? $intrinsic->height : ($this->length($box->image->height.'px', $availableHeight, $font, 'height', true) ?? 0.0);
            $ratio = null !== $intrinsic ? $intrinsic->aspectRatio : $box->image->width / $box->image->height;
            $fit = $style['object-fit'] ?? 'fill';
            $quantize = null !== $this->layoutMetrics ? fn (array $size): array => [$this->layoutMetrics->constrainLength($size[0], 'image-object-size', false), $this->layoutMetrics->constrainLength($size[1], 'image-object-size', true)] : null;
            [$drawWidth, $drawHeight] = ObjectGeometry::size($target->width, $target->height, $ratio, $fit, $naturalWidth, $naturalHeight, $quantize);
            $positionLength = fn (string $value, bool $axis): ?float => str_contains($value, '%') ? null : $this->length($value, $axis ? $target->height : $target->width, $font, 'object-position', $axis);
            $position = GradientParser::position(\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['object-position'] ?? '50% 50%'), $positionLength) ?? [['percent' => 50.0, 'offset' => 0.0, 'fromEnd' => false], ['percent' => 50.0, 'offset' => 0.0, 'fromEnd' => false]];
            $drawX = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::axis($target->x, $target->width, $drawWidth, $position[0], 'no-repeat')['start'];
            $drawY = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::axis($target->y, $target->height, $drawHeight, $position[1], 'no-repeat')['start'];
            $drawX = $target->x + ($this->layoutMetrics?->constrainLength($drawX - $target->x, 'object-position', false) ?? $drawX - $target->x);
            $drawY = $target->y + ($this->layoutMetrics?->constrainLength($drawY - $target->y, 'object-position', true) ?? $drawY - $target->y);
            if (null !== $box->replacedContent) {
                $contentLayout = $drawWidth > 0 && $drawHeight > 0 ? $box->replacedContent->layout($drawWidth, $drawHeight) : new \Symfony\Component\HtmlRenderer\Content\ContentLayout(0.0, new \Symfony\Component\HtmlRenderer\Paint\DisplayList());
                $surfaceBackground = $contentLayout->background;
                if (null !== $surfaceBackground) {
                    $contentLayout = new \Symfony\Component\HtmlRenderer\Content\ContentLayout($contentLayout->height, $contentLayout->displayList, $contentLayout->clipToBounds);
                }
                $drawHeight = $contentLayout->height;
            }
            $draw = new Fragment($box->node, new Rect($drawX, $drawY, $drawWidth, $drawHeight), array_intersect_key($style, array_flip(['color', 'visibility', 'pointer-events', 'image-rendering', 'image-orientation'])), null, [], $box->image, null, [], null, null, null, null, null, null, $contentLayout);
            $children[$replacementIndex] = 'fill' === $fit && $drawX === $target->x && $drawY === $target->y && $drawWidth === $target->width && $drawHeight === $target->height ? $draw : new Fragment(null, $target, ['overflow' => 'clip'], null, [$draw]);
        }

        foreach ($positioned as $child) {
            $placeholder = new Fragment($child->node, new Rect($innerX, $innerY, 0.0, 0.0), $child->style, null, [], null, null, [], null, $child->pseudoElement, $child->topLayer);
            $this->positionedBoxes[$placeholder] = [$child, $style];
            $children[] = $placeholder;
        }
        if ('relative' === ($style['position'] ?? 'static')) {
            $dx = $this->length($style['left'] ?? 'auto', $availableWidth, $font, 'left') ?? -($this->length($style['right'] ?? '0', $availableWidth, $font, 'right') ?? 0.0);
            $dy = $this->length($style['top'] ?? 'auto', $availableHeight, $font, 'top', true) ?? -($this->length($style['bottom'] ?? '0', $availableHeight, $font, 'bottom', true) ?? 0.0);
            $x += $dx;
            $y += $dy;
            $style['-renderer-relative-y'] = (string) $dy;
            $children = array_map(fn (Fragment $fragment): Fragment => $this->translate($fragment, $dx, $dy), $children);
        }

        $scroll = null;
        if (null !== $scrollbarKey || \in_array($style['overflow-x'] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll', 'hidden'], true) || \in_array($style['overflow-y'] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll', 'hidden'], true)) {
            $policy = null === $scrollbarKey ? ['x' => false, 'y' => false, 'lock-x' => false, 'lock-y' => false] : $this->scrollbars[$scrollbarKey];
            $reserved[2] = min($reserved[2], max(0.0, $height - $boxVertical));
            $paddingBox = new Rect($x + $borders[3], $y + $borders[0], max(0.0, $width - $borders[1] - $borders[3]), max(0.0, $height - $borders[0] - $borders[2]));
            $port = $this->scrollPort($paddingBox, $reserved);
            $content = new Rect($port->x, $port->y, $port->width, max($port->height, $padding[0] + $contentHeight + $padding[2]));
            $scroll = new ScrollGeometry($port, $content, 0.0, 0.0, true, true, $reserved, $policy['x'], $policy['y'], $policy['lock-x'] || $policy['lock-y']);
        }

        return new Fragment($box->node, new Rect($x, $y, $width, $height), $style, null, $children, null, null, [], null, $box->pseudoElement, $box->topLayer, $scroll, null === $box->borderImage ? null : new BorderImageSource($box->borderImage), $box->anchor, null, $surfaceBackground);
    }

    private function colorAlias(string $value, string $scheme): array|false|null
    {
        if (null !== $this->colorLookup && \is_array($color = ($this->colorLookup)($value, $scheme))) {
            return $color;
        }
        if (!isset($this->colors[$value]) || \is_array(ColorValue::parse($value, $scheme))) {
            return null;
        }

        return ColorValue::parse($this->colors[$value], $scheme, null === $this->colorLookup ? null : fn (string $name) => ($this->colorLookup)($name, $scheme));
    }

    private function backgrounds(array $style, Rect $bounds, FontStyle $font, ?Fragment $fragment = null): array
    {
        if (isset($style['background'])) {
            $style += \Symfony\Component\HtmlRenderer\Css\BackgroundParser::components($style['background']);
        }
        $source = $style['background-image'] ?? 'none';
        if ('none' === strtolower(trim($source))) {
            return [];
        }
        $scheme = ColorScheme::forced($style['color-scheme'] ?? '') ?? $this->preferredColorScheme;
        $alias = fn (string $value) => $this->colorAlias($value, $scheme);
        $current = ColorValue::parse($style['color'] ?? 'black', $scheme, $alias);
        $current = \is_array($current) ? $current : [[0.0, 0.0, 0.0], 1.0];
        $color = static fn (string $value) => ColorValue::parse($value, $scheme, static fn (string $name) => 'currentcolor' === $name ? $current : $alias($name));
        $length = fn (string $value, bool $vertical): ?float => str_contains($value, '%') ? null : $this->length($value, $vertical ? $bounds->height : $bounds->width, $font, 'background-image', $vertical, false);
        $linear = fn (string $value, bool $vertical): ?array => Length::linear(fn (float $base): ?float => $this->length($value, $base, $font, 'background-image', $vertical, false));
        $out = [];
        $layers = \array_slice(Selector::splitTopLevel($source), 0, 32);
        foreach ($layers as $layerIndex => $layer) {
            $selected = \Symfony\Component\HtmlRenderer\Css\ImageSource::imageSet($layer);
            $imageSource = $selected[0] ?? $layer;
            $layoutLength = fn (string $value, bool $vertical): ?float => str_contains($value, '%') ? null : $this->length($value, $vertical ? $bounds->height : $bounds->width, $font, 'background-layout', $vertical);
            $part = static fn (string $property, string $fallback) => \Symfony\Component\HtmlRenderer\Css\BackgroundParser::part($property, $style[$property] ?? $fallback, $layoutLength, $layerIndex);
            $origin = match ($part('background-origin', 'padding-box')) {
                'content-box' => $fragment?->getContentBox() ?? $bounds,
                'border-box' => $bounds,
                default => $fragment?->getPaddingBox() ?? $bounds,
            };
            $originX = $origin->x - $bounds->x;
            $originY = $origin->y - $bounds->y;
            $size = $part('background-size', 'auto') ?? [null, null];
            $position = $part('background-position', '0% 0%') ?? [['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false], ['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false]];
            $repeat = $part('background-repeat', 'repeat') ?? ['repeat', 'repeat'];
            foreach (['x', 'y'] as $axis => $name) {
                $position[$axis] = $part('background-position-'.$name, '') ?? $position[$axis];
                $repeat[$axis] = $part('background-repeat-'.$name, '') ?? $repeat[$axis];
            }
            $dimension = static fn (?array $value, float $area): ?float => null === $value ? null : max(0.0, $area * $value['percent'] / 100 + $value['offset']);
            if (null !== $url = \Symfony\Component\HtmlRenderer\Css\ImageSource::url($imageSource)) {
                if (null === $image = $this->resources?->image($url)) {
                    continue;
                }
                $resolution = $selected[1] ?? 1.0;
                $naturalWidth = $image->width / $resolution;
                $naturalHeight = $image->height / $resolution;
                $naturalWidth = $this->layoutMetrics?->constrainLength($naturalWidth, 'background-intrinsic-width', false) ?? $naturalWidth;
                $naturalHeight = $naturalWidth * $image->height / $image->width;
                $naturalHeight = $this->layoutMetrics?->constrainLength($naturalHeight, 'background-intrinsic-height', true) ?? $naturalHeight;
                [$width, $height] = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::imageSize($naturalWidth, $naturalHeight, $origin->width, $origin->height, $dimension($size[0], $origin->width), $dimension($size[1], $origin->height), $size[2] ?? null, $image->height / $image->width);
                $width = $this->layoutMetrics?->constrainLength($width, 'background-size', false) ?? $width;
                $height = $this->layoutMetrics?->constrainLength($height, 'background-size', true) ?? $height;
                if ($width <= 0 || $height <= 0) {
                    continue;
                }
                $axes = $this->backgroundAxes($originX, $originY, $origin->width, $origin->height, $width, $height, $position, $repeat);
                $out[] = ['kind' => 'image', 'image' => $image, 'pattern' => $axes, 'clip' => $part('background-clip', 'border-box') ?? 'border-box', 'layer-index' => $layerIndex];
                continue;
            }
            foreach (\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($layer) as $token) {
                if (null !== $gradient = GradientParser::parse($token, $length, $color, $linear)) {
                    $width = $dimension($size[0], $origin->width) ?? $origin->width;
                    $height = $dimension($size[1], $origin->height) ?? $origin->height;
                    if (isset($size[2])) {
                        [$width, $height] = [$origin->width, $origin->height];
                    }
                    $width = $this->layoutMetrics?->constrainLength($width, 'background-size', false) ?? $width;
                    $height = $this->layoutMetrics?->constrainLength($height, 'background-size', true) ?? $height;
                    $axes = $this->backgroundAxes($originX, $originY, $origin->width, $origin->height, $width, $height, $position, $repeat);
                    if ($width > 0 && $height > 0 && null !== $model = GradientGeometry::resolveModel($gradient, $axes['x']['size'], $axes['y']['size'], $color)) {
                        if (\count($layers) > 1) {
                            $model['layer-index'] = $layerIndex;
                        }
                        $out[] = ['kind' => 'gradient', 'gradient' => $model, 'pattern' => $axes, 'clip' => $part('background-clip', 'border-box') ?? 'border-box', 'layer-index' => $layerIndex];
                    }
                    break;
                }
            }
        }

        return $out;
    }

    private function backgroundAxes(float $x, float $y, float $areaWidth, float $areaHeight, float $width, float $height, array $position, array $repeat): array
    {
        $axes = [
            'x' => \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::axis($x, $areaWidth, $width, $position[0], $repeat[0]),
            'y' => \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::axis($y, $areaHeight, $height, $position[1], $repeat[1]),
        ];
        foreach (['x' => $x, 'y' => $y] as $name => $start) {
            $offset = $axes[$name]['start'] - $start;
            $axes[$name]['start'] = $start + ($this->layoutMetrics?->constrainLength($offset, 'background-position', 'y' === $name) ?? $offset);
        }

        return $axes;
    }

    private function rubyPair(array $boxes, float $x, float $y, array $style): array
    {
        $runs = [];
        foreach ($boxes as $box) {
            $font = $this->font($box->style, $style);
            $width = $this->inlineOuterWidth($box, $this->viewportWidth, $font);
            $runs[] = $this->place($box, 0.0, 0.0, $width, $this->viewportHeight, $style, $width);
        }
        if (!$runs) {
            return [[], 0.0, 0.0, 0.0];
        }
        $base = $runs[0];
        $annotation = $runs[1] ?? null;
        $geometry = RubyGeometry::place($base->bounds->width, $annotation?->bounds->width ?? 0.0, $base->bounds->height, $annotation?->bounds->height ?? 0.0, 'under' === ($style['ruby-position'] ?? 'over'), 'start' === ($style['ruby-align'] ?? 'space-around'));
        $children = [$this->translate($base, $x + $geometry['base'][0], $y + $geometry['base'][1])];
        if (null !== $annotation) {
            $children[] = $this->translate($annotation, $x + $geometry['annotation'][0], $y + $geometry['annotation'][1]);
        }

        return [$children, $geometry['width'], $geometry['height'], $geometry['base'][1] + $this->inlineBaseline($base)];
    }

    private function inlineBaseline(Fragment $fragment): float
    {
        if ('1' === ($fragment->style['-renderer-replaced'] ?? null)) {
            return $fragment->bounds->y + $fragment->bounds->height;
        }
        if (isset($fragment->style['-renderer-ruby-baseline'])) {
            return $fragment->bounds->y + (float) $fragment->style['-renderer-ruby-baseline'];
        }
        if (null !== $fragment->text) {
            return $fragment->bounds->y + (float) ($fragment->style['-renderer-baseline'] ?? $fragment->bounds->height);
        }
        if ('visible' === ($fragment->style['overflow-y'] ?? $fragment->style['overflow'] ?? 'visible')) {
            for ($index = \count($fragment->children) - 1; $index >= 0; --$index) {
                $child = $fragment->children[$index];
                if (null === $child->topLayer && !\in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)) {
                    return $this->inlineBaseline($child) - (float) ($child->style['-renderer-relative-y'] ?? 0);
                }
            }
        }

        return $fragment->bounds->y + $fragment->bounds->height;
    }

    /** @return array{Fragment, bool} The formatted fragment and whether its collapsible tail is empty. */
    private function trimLineEnd(Fragment $fragment): array
    {
        $style = $fragment->style;
        if ('inline' !== ($style['display'] ?? 'inline') || !\in_array($style['white-space'] ?? 'normal', ['normal', 'nowrap', 'pre-line'], true) || null !== $fragment->image || null !== $fragment->content || null !== $fragment->run || null !== $fragment->topLayer || (float) ($style['padding-right'] ?? 0) > 0 || (float) ($style['border-right-width'] ?? 0) > 0) {
            return [$fragment, false];
        }
        $text = $fragment->text;
        $children = $fragment->children;
        $width = $fragment->bounds->width;
        if (null !== $text) {
            $trimmed = rtrim($text, ' ');
            if ($trimmed === $text) {
                return [$fragment, '' === $text];
            }
            $text = $trimmed;
            $width = $this->measureText($text, $this->font($style, []))->width;
            $empty = '' === $text;
        } else {
            $empty = true;
            $changed = false;
            for ($index = \count($children) - 1; $index >= 0; --$index) {
                $child = $children[$index];
                if (null !== $child->topLayer || \in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true) || 'none' !== ($child->style['float'] ?? 'none')) {
                    continue;
                }
                [$children[$index], $empty] = $this->trimLineEnd($child);
                $changed = $changed || $children[$index] !== $child;
                if (!$empty) {
                    break;
                }
            }
            if (!$changed) {
                return [$fragment, $empty && 0.0 === $width];
            }
            if ('auto' === ($style['width'] ?? 'auto') && \in_array($style['min-width'] ?? 'auto', ['auto', '0', '0px'], true)) {
                $right = $fragment->bounds->x;
                foreach ($children as $child) {
                    if ($child->bounds->width > 0 && null === $child->topLayer && !\in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true) && 'none' === ($child->style['float'] ?? 'none')) {
                        $right = max($right, $child->bounds->x + $child->bounds->width);
                    }
                }
                $width = max(0.0, $right - $fragment->bounds->x);
            }
        }
        $bounds = new Rect($fragment->bounds->x, $fragment->bounds->y, $width, $fragment->bounds->height);

        return [new Fragment($fragment->node, $bounds, $style, $text, $children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline), $empty && 0.0 === $width];
    }

    /** @return array{float, float, TextMetrics} */
    private function lineStrut(FontStyle $font): array
    {
        $metrics = $this->measureText('', $font);
        $ascent = ($font->lineHeight - $metrics->ascent - $metrics->descent) / 2 + $metrics->ascent;

        return [$ascent, $font->lineHeight - $ascent, $metrics];
    }

    /** @return array{float, float, float, string, float} */
    private function inlineVerticalMetrics(Fragment $fragment, FontStyle $font, TextMetrics $metrics, array $style): array
    {
        $align = $fragment->style['vertical-align'] ?? 'baseline';
        $baseline = $this->inlineBaseline($fragment) - $fragment->bounds->y;
        $height = $fragment->bounds->height;
        $childFont = $this->font($fragment->style, $style);
        $shift = match ($align) {
            'super' => $font->size * .5,
            'sub' => -$font->size * .25,
            'middle' => $height / 2 + $font->size / 4 - $baseline,
            'text-top' => $metrics->ascent - $baseline,
            'text-bottom' => $height - $metrics->descent - $baseline,
            default => $this->length($align, $childFont->lineHeight, $childFont, 'vertical-align', true, false) ?? 0.0,
        };
        $applied = $this->layoutMetrics?->constrainLength($shift, 'vertical-align-'.$align, true) ?? $shift;

        return [$baseline, $shift, $applied, $align, $height];
    }

    /**
     * @param array{float, float, float}                $state
     * @param array{float, float, float, string, float} $item
     *
     * @return array{float, float, float}
     */
    private static function extendLineMetrics(array $state, array $item): array
    {
        if (\in_array($item[3], ['top', 'bottom'], true)) {
            $state[2] = max($state[2], $item[4]);
        } else {
            $state[0] = max($state[0], $item[0] + $item[2]);
            $state[1] = max($state[1], $item[4] - $item[0] - $item[2]);
        }

        return $state;
    }

    /** @param array{float, float, float} $state */
    private function lineBoxHeight(array $state): float
    {
        $required = max(0.0, $state[0] + $state[1], $state[2]);

        return $this->layoutMetrics?->constrainLength($required, 'line-box-height', true) ?? $required;
    }

    private function alignLine(array &$children, array &$indices, float $width, array $style, float &$lineHeight, float $lineTop, bool $trimEnd = true, bool $lastLine = false, array &$inlineOffsets = [], float &$floatLineShift = 0.0): void
    {
        if (!$indices) {
            $inlineOffsets = [];
            $floatLineShift = 0.0;

            return;
        }
        if ($inlineOffsets || 0.0 !== $floatLineShift) {
            foreach ($indices as $index) {
                $dx = $floatLineShift - ($inlineOffsets[$index] ?? 0.0);
                if (0.0 !== $dx) {
                    $children[$index] = $this->translate($children[$index], $dx, 0.0);
                }
            }
            $inlineOffsets = [];
            $floatLineShift = 0.0;
        }
        $alignment = $indices;
        if ($trimEnd) {
            foreach (array_reverse(array_keys($indices)) as $tail) {
                $index = $indices[$tail];
                [$children[$index], $empty] = $this->trimLineEnd($children[$index]);
                if (!$empty) {
                    break;
                }
                unset($alignment[$tail]);
            }
        }
        $font = $this->font($style, []);
        [$ascent, $descent, $metrics] = $this->lineStrut($font);
        $state = [$ascent, $descent, 0.0];
        $items = [];
        foreach ($indices as $index) {
            $items[$index] = $this->inlineVerticalMetrics($children[$index], $font, $metrics, $style);
            $state = self::extendLineMetrics($state, $items[$index]);
        }
        $ascent = $state[0];
        $lineHeight = $this->lineBoxHeight($state);
        $left = $alignment ? min(array_map(static fn (int $index): float => $children[$index]->bounds->x, $alignment)) : $children[$indices[0]]->bounds->x;
        $right = $alignment ? max(array_map(static fn (int $index): float => $children[$index]->bounds->x + $children[$index]->bounds->width, $alignment)) : $left;
        $align = $style['text-align'] ?? 'start';
        if ($lastLine) {
            $last = $style['text-align-last'] ?? 'auto';
            $align = 'auto' === $last ? ('justify' === $align ? 'start' : $align) : $last;
        }
        if ('justify' === $align && 'none' !== ($style['text-justify'] ?? 'auto') && \count($alignment) > 1 && $width > $right - $left) {
            $fonts = new \WeakMap();
            $measure = function (string $text, Fragment $fragment) use ($fonts): TextMetrics {
                $fonts[$fragment] ??= $this->font($fragment->style, []);

                return $this->measureText($text, $fonts[$fragment]);
            };
            $line = array_map(static fn (int $index): Fragment => $children[$index], $indices);
            $line = InlineJustification::apply($line, $width - $right + $left, $measure, $this->translate(...), fn (array $lengths): array => $this->distribute($lengths, false), $this->remainingJustificationFragments, $style['text-justify'] ?? 'auto');
            foreach ($indices as $index => $child) {
                $children[$child] = $line[$index];
            }
            $right = max(array_map(static fn (Fragment $fragment): float => $fragment->bounds->x + $fragment->bounds->width, $line));
        }
        $offset = InlineAlignment::offset($width, $right - $left, $align, $style['direction'] ?? 'ltr');
        foreach ($indices as $index) {
            $fragment = $children[$index];
            [$baseline, $shift, $applied, $align] = $items[$index];
            $top = match ($align) {
                'top' => $lineTop,
                'bottom' => $lineTop + $lineHeight - $fragment->bounds->height,
                default => $lineTop + $ascent - $baseline - $applied,
            };
            $top += (float) ($fragment->style['-renderer-relative-y'] ?? 0);
            $top = $this->layoutMetrics?->constrainLength($top, 'inline-baseline-position', true) ?? $top;
            $dy = $top - $fragment->bounds->y;
            if (0.0 !== $offset || 0.0 !== $dy) {
                $fragment = $this->translate($fragment, $offset, $dy);
            }
            if (0.0 !== $shift) {
                $fragment = new Fragment($fragment->node, $fragment->bounds, array_replace($fragment->style, ['-renderer-baseline-shift' => (string) $shift]), $fragment->text, $fragment->children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
            }
            $children[$index] = $fragment;
        }
        $indices = [];
    }

    private function position(Fragment $fragment, Rect $containing, ?Rect $anchor = null, array $parentMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0], ?Rect $fixedContaining = null, ?Rect $referenceOverride = null): Fragment
    {
        $anchorSource = null;
        if (isset($this->positionedBoxes[$fragment])) {
            [$box, $inherited] = $this->positionedBoxes[$fragment];
            if (null !== $box->anchor || $box->anchorReferences || array_any($box->style, static fn (string $value): bool => str_contains($value, 'anchor(') || str_contains($value, 'anchor-size('))) {
                $anchorSource = [$box, $inherited];
                $this->hasAnchors = true;
            }
            $reference = $referenceOverride ?? ('picker' === $box->pseudoElement && null !== $anchor ? $anchor : (null !== $box->topLayer ? new Rect(0.0, 0.0, $this->viewportWidth, $this->viewportHeight) : ('fixed' === ($box->style['position'] ?? '') ? ($fixedContaining ?? new Rect(0.0, 0.0, $this->viewportWidth, $this->viewportHeight)) : $containing)));
            $font = $this->font($box->style, $inherited);
            $style = $box->style;
            if (null !== $box->topLayer) {
                $style['position'] = 'fixed';
            }
            $left = $this->length($style['left'] ?? 'auto', $reference->width, $font, 'left');
            $right = $this->length($style['right'] ?? 'auto', $reference->width, $font, 'right');
            $top = $this->length($style['top'] ?? 'auto', $reference->height, $font, 'top', true);
            $bottom = $this->length($style['bottom'] ?? 'auto', $reference->height, $font, 'bottom', true);
            $forcedWidth = null;
            if (preg_match('/^(?:(?:-webkit-|-moz-)?(?:min-content|max-content|fit-content)|fit-content\()/i', $style['width'] ?? 'auto')) {
                $margin = $this->edges($style, 'margin', $reference->width, $font);
                $padding = $this->padding($box, $style, $reference->width, $font);
                $borders = $this->borders($style, $reference->width, $font);
                $chrome = $padding[1] + $padding[3] + $borders[1] + $borders[3];
                $available = max(0.0, $reference->width - ($left ?? 0.0) - ($right ?? 0.0) - $margin[1] - $margin[3] - $chrome);
                if (null !== $intrinsic = $this->intrinsicKeywordWidth($box, $font, $style['width'], $available, $reference->width, $chrome)) {
                    $forcedWidth = $this->layoutMetrics?->constrainLength($intrinsic + $chrome, 'width', false) ?? $intrinsic + $chrome;
                }
            }
            if (null === $box->replacedContent && (!isset($style['width']) || 'auto' === $style['width'])) {
                $margin = $this->edges($style, 'margin', $reference->width, $font);
                if (null !== $left && null !== $right) {
                    $forcedWidth = max(0.0, $reference->width - $left - $right - $margin[1] - $margin[3]);
                } else {
                    $padding = $this->padding($box, $style, $reference->width, $font);
                    $borders = $this->borders($style, $reference->width, $font);
                    $chrome = $padding[1] + $padding[3] + $borders[1] + $borders[3];
                    $available = max(0.0, $reference->width - ($left ?? 0.0) - ($right ?? 0.0) - $margin[1] - $margin[3]);
                    $forcedWidth = min(max($this->minimumWidth($box, $font) + $chrome, $available), $this->intrinsicWidth($box, $font) + $chrome);
                }
            }
            if ('picker' === $box->pseudoElement) {
                $this->hasPickers = true;
                if ('100%' === ($style['min-width'] ?? '')) {
                    $style['min-width'] = min($reference->width, $this->viewportWidth).'px';
                }
                $padding = $this->padding($box, $style, $reference->width, $font);
                $borders = $this->borders($style, $reference->width, $font);
                $natural = 0.0;
                foreach ($box->children as $option) {
                    $optionFont = $this->font($option->style, array_replace($inherited, $style));
                    $optionPadding = $this->padding($option, $option->style, $reference->width, $optionFont);
                    $optionBorders = $this->borders($option->style, $reference->width, $optionFont);
                    $natural = max($natural, $this->intrinsicWidth($option, $optionFont) + $optionPadding[1] + $optionPadding[3] + $optionBorders[1] + $optionBorders[3]);
                }
                if (\in_array($style['width'] ?? 'auto', ['auto', 'max-content'], true)) {
                    $forcedWidth = min($this->viewportWidth, max($reference->width, $natural + $padding[1] + $padding[3] + $borders[1] + $borders[3]));
                }
            }
            $forcedHeight = (!isset($style['height']) || 'auto' === $style['height']) && null !== $top && null !== $bottom ? max(0.0, $reference->height - $top - $bottom) : null;
            $display = null !== $box->topLayer ? match ($box->display) {
                'inline', 'inline-block' => 'block',
                'inline-flex' => 'flex',
                'inline-grid' => 'grid',
                'inline-table' => 'table',
                default => $box->display,
            } : $box->display;
            $box = $this->copiedBox($box, new Box($box->node, $display, $style, $box->children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries));
            $placed = $this->place($box, 0.0, 0.0, $reference->width, $reference->height, $inherited, $forcedWidth, [], $forcedHeight);
            $x = null !== $left ? $reference->x + $left : (null !== $right ? $reference->x + $reference->width - $right - $placed->bounds->width : (null !== $box->topLayer ? 0.0 : $fragment->bounds->x));
            $y = null !== $top ? $reference->y + $top : (null !== $bottom ? $reference->y + $reference->height - $bottom - $placed->bounds->height : (null !== $box->topLayer ? 0.0 : $fragment->bounds->y));
            $margin = $this->edges($style, 'margin', $reference->width, $font);
            foreach ([['left', 'right', $left, $right, $reference->width, $placed->bounds->width, 3, 1], ['top', 'bottom', $top, $bottom, $reference->height, $placed->bounds->height, 0, 2]] as $axis => [$start, $end, $before, $after, $room, $size, $startIndex, $endIndex]) {
                $autoStart = 'auto' === ($style['margin-'.$start] ?? $style['margin'] ?? '0');
                $autoEnd = 'auto' === ($style['margin-'.$end] ?? $style['margin'] ?? '0');
                if (null !== $before && null !== $after && $autoStart) {
                    $free = max(0.0, $room - $before - $after - $size - $margin[$startIndex] - $margin[$endIndex]);
                    $shift = $free / ($autoEnd ? 2 : 1);
                    0 === $axis ? $x += $shift : $y += $shift;
                }
            }
            $fragment = $this->translate($placed, $x, $y);
        }
        if ('static' !== ($fragment->style['position'] ?? 'static') || Containment::fixed($fragment->style)) {
            $containing = $fragment->scroll->port ?? $fragment->getPaddingBox();
        }
        if (Containment::fixed($fragment->style)) {
            $fixedContaining = $containing;
        }
        $font = $this->font($fragment->style, []);
        $transform = Transform::resolve($fragment->style, $fragment->bounds, fn (string $value, bool $vertical): ?float => $this->length($value, $vertical ? $fragment->bounds->height : $fragment->bounds->width, $font, 'transform', $vertical));
        $parentMatrix = null !== $fragment->topLayer ? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0] : $parentMatrix;
        $matrix = null === $transform ? $parentMatrix : \Symfony\Component\HtmlRenderer\Vector\Geometry::multiply($parentMatrix, $transform);
        $children = array_map(fn (Fragment $child): Fragment => $this->position($child, $containing, 'picker' === $child->pseudoElement ? $this->transformedBounds($fragment->bounds, $matrix) : null, $matrix, $fixedContaining), $fragment->children);

        $scroll = null;
        $overflowX = $fragment->style['overflow-x'] ?? $fragment->style['overflow'] ?? 'visible';
        $overflowY = $fragment->style['overflow-y'] ?? $fragment->style['overflow'] ?? 'visible';
        $scrollX = \in_array($overflowX, ['auto', 'scroll', 'hidden'], true);
        $scrollY = \in_array($overflowY, ['auto', 'scroll', 'hidden'], true);
        if ($scrollX || $scrollY) {
            $port = $fragment->scroll->port ?? $fragment->getPaddingBox();
            $extent = null === $fragment->scroll ? $port : $this->union($port, $fragment->scroll->content);
            foreach ($children as $child) {
                if ('table-border' === $child->pseudoElement) {
                    continue;
                }
                if (null !== $overflow = $this->overflowBounds($child, Containment::fixed($fragment->style))) {
                    $extent = $this->union($extent, $overflow);
                }
            }
            $scroll = new ScrollGeometry($port, $extent, $scrollX ? (float) ($fragment->style['--html-scroll-x'] ?? 0) : 0.0, $scrollY ? (float) ($fragment->style['--html-scroll-y'] ?? 0) : 0.0, $scrollX, $scrollY, $fragment->scroll->reserved ?? [0.0, 0.0, 0.0, 0.0], $fragment->scroll->horizontalBar ?? false, $fragment->scroll->verticalBar ?? false, $fragment->scroll->cycleResolved ?? false);
            $scroll = $this->snapScroll($fragment, $children, $scroll);
            if (isset($fragment->style['-renderer-scrollbar-key'])) {
                $key = (int) $fragment->style['-renderer-scrollbar-key'];
                $policy = $this->scrollbars[$key];
                $this->requiredBars[$key] = [('scroll' === $overflowX || 'auto' === $overflowX && $scroll->maxX > 0) && $policy['horizontal'] > 0, ('scroll' === $overflowY || 'auto' === $overflowY && $scroll->maxY > 0) && $policy['vertical'] > 0];
            }
            if ($scroll->offsetX || $scroll->offsetY) {
                $children = array_map(fn (Fragment $child): Fragment => 'table-border' === $child->pseudoElement ? $child : $this->scrollTranslate($child, -$scroll->offsetX, -$scroll->offsetY, Containment::fixed($fragment->style)), $children);
            }
        }
        $borderImage = $fragment->borderImage;
        if (null === $borderImage && 'none' !== ($fragment->style['border-image-source'] ?? 'none')) {
            $sources = $this->backgrounds(array_replace($fragment->style, ['background-image' => $fragment->style['border-image-source'], 'background-origin' => 'border-box', 'background-size' => '100% 100%', 'background-position' => '0% 0%', 'background-repeat' => 'no-repeat']), $fragment->bounds, $font);
            if ($sources) {
                $borderImage = new BorderImageSource($sources[0]['gradient']);
            }
        }

        $style = $fragment->style;
        $outline = null;
        if (!\in_array($style['outline-style'] ?? 'none', ['none', 'hidden'], true)) {
            $value = $style['outline-width'] ?? 'medium';
            $value = match (strtolower(trim($value))) {
                'thin' => '1px', 'medium' => '3px', 'thick' => '5px', default => $value,
            };
            $widthX = max(0.0, $this->length($value, $fragment->bounds->width, $font, 'outline-width', false) ?? 0.0);
            $offsetX = $this->length($style['outline-offset'] ?? '0', $fragment->bounds->width, $font, 'outline-offset', false) ?? 0.0;
            $widthY = max(0.0, $this->length($value, $fragment->bounds->height, $font, 'outline-width', true) ?? 0.0);
            $offsetY = $this->length($style['outline-offset'] ?? '0', $fragment->bounds->height, $font, 'outline-offset', true) ?? 0.0;
            $outline = new OutlineGeometry($widthX, $widthY, $offsetX, $offsetY);
        }
        if (isset($style['border-radius']) || isset($style['border-top-left-radius']) || isset($style['border-top-right-radius']) || isset($style['border-bottom-right-radius']) || isset($style['border-bottom-left-radius'])) {
            $radii = [];
            foreach (['top-left', 'top-right', 'bottom-right', 'bottom-left'] as $corner) {
                $property = 'border-'.$corner.'-radius';
                $value = $style[$property] ?? \Symfony\Component\HtmlRenderer\Css\DeclarationExpander::parentValue($style, $property) ?? '0';
                $values = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split(implode(' ', Selector::splitTopLevel($value, '/')));
                $radii[] = [max(0.0, $this->length($values[0] ?? '0', $fragment->bounds->width, $font, $property) ?? 0.0), max(0.0, $this->length($values[1] ?? $values[0] ?? '0', $fragment->bounds->height, $font, $property, true) ?? 0.0)];
            }
            $factor = 1.0;
            foreach ([[$fragment->bounds->width, $radii[0][0] + $radii[1][0]], [$fragment->bounds->width, $radii[3][0] + $radii[2][0]], [$fragment->bounds->height, $radii[0][1] + $radii[3][1]], [$fragment->bounds->height, $radii[1][1] + $radii[2][1]]] as [$extent, $sum]) {
                if ($sum > 0) {
                    $factor = min($factor, $extent / $sum);
                }
            }
            foreach (['top-left', 'top-right', 'bottom-right', 'bottom-left'] as $index => $corner) {
                $style['border-'.$corner.'-radius'] = $radii[$index][0] * $factor.'px '.$radii[$index][1] * $factor.'px';
            }
        }

        $result = new Fragment($fragment->node, $fragment->bounds, $style, $fragment->text, $children, $fragment->image, $transform, $this->backgrounds($fragment->style, $fragment->bounds, $font, $fragment), $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $scroll, $borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $outline);
        if (null !== $anchorSource) {
            $this->anchorSources[$result] = $anchorSource;
        }

        return $result;
    }

    private function transformedBounds(Rect $bounds, array $matrix): Rect
    {
        $points = [];
        foreach ([[$bounds->x, $bounds->y], [$bounds->x + $bounds->width, $bounds->y], [$bounds->x, $bounds->y + $bounds->height], [$bounds->x + $bounds->width, $bounds->y + $bounds->height]] as [$x, $y]) {
            $points[] = [$matrix[0] * $x + $matrix[2] * $y + $matrix[4], $matrix[1] * $x + $matrix[3] * $y + $matrix[5]];
        }
        $x = min(array_column($points, 0));
        $y = min(array_column($points, 1));

        return new Rect($x, $y, max(array_column($points, 0)) - $x, max(array_column($points, 1)) - $y);
    }

    private function anchorLayers(Fragment $root): Fragment
    {
        $anchors = new \SplObjectStorage();
        $owners = new \SplObjectStorage();
        $principalPaths = new \SplObjectStorage();
        $layers = [];
        $collect = function (Fragment $fragment, array $parent, array &$layers, array $path = [], ?string $owner = null, ?Rect $containing = null, ?Rect $fixedContaining = null) use (&$collect, $anchors, $owners, $principalPaths): void {
            $containing ??= new Rect(0, 0, $this->viewportWidth, $this->viewportHeight);
            $pathKey = implode('.', $path);
            if (null !== $fragment->anchor || isset($this->anchorSources[$fragment]) || 'picker' === $fragment->pseudoElement) {
                $source = $this->anchorSources[$fragment] ?? null;
                $key = null !== $source ? 'box-'.spl_object_id($this->boxOrigins[$source[0]] ?? $source[0]) : ('picker' === $fragment->pseudoElement && null !== $fragment->node ? 'picker-'.spl_object_id($fragment->node) : 'path-'.$pathKey);
                $layers[$key] = ['path' => $path, 'anchor' => $fragment->anchor ?? ('picker' === $fragment->pseudoElement ? $fragment->node : null), 'parent' => $owner, 'matrix' => $parent, 'source' => $this->anchorSources[$fragment] ?? null, 'containing' => $containing, 'fixed-containing' => $fixedContaining];
                $owner = $key;
            }
            $parent = null !== $fragment->topLayer ? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0] : $parent;
            $matrix = null === $fragment->transform ? $parent : \Symfony\Component\HtmlRenderer\Vector\Geometry::multiply($parent, $fragment->transform);
            if (null === $fragment->pseudoElement && $fragment->node instanceof \Dom\Element && (!isset($principalPaths[$fragment->node]) || $principalPaths[$fragment->node] === $pathKey)) {
                $principalPaths[$fragment->node] = $pathKey;
                $anchors[$fragment->node] = $this->transformedBounds($fragment->bounds, $matrix);
                $owners[$fragment->node] = $owner;
            }
            if ('static' !== ($fragment->style['position'] ?? 'static') || Containment::fixed($fragment->style)) {
                $containing = $fragment->scroll->port ?? $fragment->getPaddingBox();
            }
            if (Containment::fixed($fragment->style)) {
                $fixedContaining = $containing;
            }
            foreach ($fragment->children as $index => $child) {
                $collect($child, $matrix, $layers, [...$path, $index], $owner, $containing, $fixedContaining);
            }
        };
        $collect($root, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $layers);
        $sources = $this->anchorSources;
        $replace = static function (Fragment $fragment, array $path, Fragment $replacement) use (&$replace, $sources): Fragment {
            if (!$path) {
                return $replacement;
            }
            $index = array_shift($path);
            $children = $fragment->children;
            $children[$index] = $replace($children[$index], $path, $replacement);

            $result = new Fragment($fragment->node, $fragment->bounds, $fragment->style, $fragment->text, $children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
            if (isset($sources[$fragment])) {
                $sources[$result] = $sources[$fragment];
            }

            return $result;
        };
        $states = [];
        $ready = function (array $pending, array $states, array $layers) use ($owners): bool {
            $visited = [];
            $stack = array_map(static fn (string $key): array => [$key, false], $pending);
            while ($stack) {
                [$key, $done] = array_pop($stack);
                if ($done) {
                    $visited[$key] = 2;
                    continue;
                }
                if (2 === ($states[$key] ?? 0) || 2 === ($visited[$key] ?? 0) || !isset($layers[$key])) {
                    continue;
                }
                if (1 === ($states[$key] ?? 0) || 1 === ($visited[$key] ?? 0)) {
                    return false;
                }
                $visited[$key] = 1;
                $stack[] = [$key, true];
                foreach ($this->anchorOwners($layers[$key], $owners) as $dependency) {
                    $stack[] = [$dependency, false];
                }
            }

            return true;
        };
        $place = function (string $key, Fragment &$root, array &$states, array &$layers) use (&$place, $anchors, $owners, $principalPaths, $collect, $replace, $ready): bool {
            if (isset($states[$key])) {
                return 2 === $states[$key];
            }
            $states[$key] = 1;
            if (!isset($layers[$key])) {
                return true;
            }
            $layer = $layers[$key];
            foreach ($this->anchorOwners($layer, $owners) as $before) {
                if (!$place($before, $root, $states, $layers)) {
                    return false;
                }
            }
            if (!isset($layers[$key])) {
                return true;
            }
            $layer = $layers[$key];
            $fragment = $root;
            foreach ($layer['path'] as $index) {
                $fragment = $fragment->children[$index];
            }
            if (null !== $layer['source']) {
                $resolved = $this->resolveAnchorLayout($fragment, $layer, $anchors);
                if ($resolved !== $fragment) {
                    $fragment = $resolved;
                    $root = $replace($root, $layer['path'], $fragment);
                    // Resizing can change fragment paths; stable box identities retain dependency state.
                    $layers = [];
                    $anchors->removeAll($anchors);
                    $owners->removeAll($owners);
                    $principalPaths->removeAll($principalPaths);
                    $collect($root, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $layers);
                    $layer = $layers[$key];
                }
            }
            $position = $this->anchorPosition($fragment, $layer['source'][0] ?? null, $anchors);
            if (null !== $position) {
                $fits = fn (Fragment $part, array $at): bool => $at[0] >= 0 && $at[1] >= 0 && $at[0] + $part->bounds->width <= $this->viewportWidth && $at[1] + $part->bounds->height <= $this->viewportHeight;
                $source = $layer['source'][0] ?? null;
                if (null !== $source && !$fits($fragment, $position)) {
                    $tries = $source->positionTries;
                    $order = \Symfony\Component\HtmlRenderer\Css\Anchor::order($source->style['position-try-order'] ?? '')[0] ?? \Symfony\Component\HtmlRenderer\Css\Anchor::order($source->style['position-try'] ?? '')[0] ?? 'normal';
                    if ('normal' !== $order) {
                        $axis = 'most-width' === $order ? 0 : 1;
                        $ranked = [];
                        foreach ($tries as $try) {
                            $candidate = $this->anchorTry($source, $try[0], $try[1]);
                            $room = [0.0, 0.0];
                            if (null !== $candidate->anchor && isset($anchors[$candidate->anchor])) {
                                $area = \Symfony\Component\HtmlRenderer\Css\PositionArea::parse($candidate->style['position-area'] ?? 'none', $candidate->style['direction'] ?? 'ltr', $candidate->style['writing-mode'] ?? 'horizontal-tb');
                                $room = AnchorGeometry::room($anchors[$candidate->anchor], $this->viewportWidth, $this->viewportHeight, $area, $this->anchorEdges($candidate, $candidate->anchor, $anchors), fn (float $value, bool $vertical): float => $this->layoutMetrics?->constrainLength($value / 2, 'anchor-center-offset', $vertical) ?? $value / 2);
                            }
                            $ranked[] = [$try, $room[$axis]];
                        }
                        usort($ranked, static fn (array $first, array $second): int => $second[1] <=> $first[1]);
                        $tries = array_column($ranked, 0);
                    }
                    foreach ($tries as [$override, $tactics]) {
                        $candidate = $this->anchorTry($source, $override, $tactics);
                        $candidateLayer = $layer;
                        $candidateLayer['source'] = [$candidate, $layer['source'][1]];
                        $candidateLayer['anchor'] = $candidate->anchor;
                        $dependencies = $this->anchorOwners($candidateLayer, $owners);
                        if (!$ready($dependencies, $states, $layers)) {
                            continue;
                        }
                        foreach ($dependencies as $dependency) {
                            if (!$place($dependency, $root, $states, $layers)) {
                                continue 2;
                            }
                        }
                        $layer = $layers[$key];
                        $resolved = $this->resolveAnchorLayout($fragment, $candidateLayer, $anchors, true);
                        $at = $this->anchorPosition($resolved, $candidate, $anchors);
                        if (null === $at || !$fits($resolved, $at)) {
                            continue;
                        }
                        $fragment = $resolved;
                        $position = $at;
                        $root = $replace($root, $layer['path'], $fragment);
                        $layers = [];
                        $anchors->removeAll($anchors);
                        $owners->removeAll($owners);
                        $principalPaths->removeAll($principalPaths);
                        $collect($root, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0], $layers);
                        $layer = $layers[$key];
                        break;
                    }
                }
                $position = [max(0.0, min($position[0], $this->viewportWidth - $fragment->bounds->width)), max(0.0, min($position[1], $this->viewportHeight - $fragment->bounds->height))];
            } elseif ('picker' === $fragment->pseudoElement && null !== $fragment->node && isset($anchors[$fragment->node])) {
                $anchor = $anchors[$fragment->node];
                $x = max(0.0, min($anchor->x, $this->viewportWidth - $fragment->bounds->width));
                $below = $anchor->y + $anchor->height;
                $position = [$x, $below + $fragment->bounds->height <= $this->viewportHeight ? $below : max(0.0, $anchor->y - $fragment->bounds->height)];
            }
            if (null !== $position) {
                $fragment = $this->translate($fragment, $position[0] - $fragment->bounds->x, $position[1] - $fragment->bounds->y);
                $root = $replace($root, $layer['path'], $fragment);
                // Translation preserves paths: only this subtree's principal geometry can have changed.
                $collect($fragment, $layer['matrix'], $layers, $layer['path'], $layer['parent'], $layer['containing'], $layer['fixed-containing']);
            }
            $states[$key] = 2;

            return true;
        };
        foreach (array_keys($layers) as $key) {
            $place((string) $key, $root, $states, $layers);
        }

        return $root;
    }

    private function anchorOwners(array $layer, \SplObjectStorage $owners): array
    {
        $anchor = $layer['anchor'];
        $dependencies = [$layer['parent'], null !== $anchor && isset($owners[$anchor]) ? $owners[$anchor] : null];
        $source = $layer['source'][0] ?? null;
        foreach (\Symfony\Component\HtmlRenderer\Css\Anchor::references($source->style ?? []) as $name) {
            $node = $source->anchorReferences[$name] ?? null;
            if (null !== $node && isset($owners[$node])) {
                $dependencies[] = $owners[$node];
            }
        }

        return array_values(array_unique(array_filter($dependencies, static fn (?string $key): bool => null !== $key)));
    }

    private function anchorEdges(?Box $source, \Dom\Element $anchor, \SplObjectStorage $anchors): array
    {
        $edges = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (null !== $parsed = \Symfony\Component\HtmlRenderer\Css\Anchor::edge($source?->style[$side] ?? '')) {
                $node = null === $parsed[0] ? $anchor : ($source->anchorReferences[$parsed[0]] ?? null);
                if (null !== $node && isset($anchors[$node])) {
                    $edges[$side] = [$anchors[$node], $parsed[1]];
                }
            }
        }

        return $edges;
    }

    private function anchorTry(Box $source, array $override, array $tactics): Box
    {
        $style = \Symfony\Component\HtmlRenderer\Css\Anchor::applyFallback($source->style, $override);
        $style['position-try-fallbacks'] = $tactics ? implode(' ', $tactics) : 'none';
        $name = trim($style['position-anchor'] ?? '');
        $anchor = str_starts_with($name, '--') ? ($source->anchorReferences[$name] ?? null) : $source->anchor;

        return $this->copiedBox($source, new Box($source->node, $source->display, $style, $source->children, $source->text, $source->image, $source->selection, $source->pseudoElement, $source->topLayer, $source->borderImage, $anchor, $source->replacedContent, $source->anchorReferences));
    }

    private function anchorPosition(Fragment $fragment, ?Box $source, \SplObjectStorage $anchors): ?array
    {
        if (null === $fragment->anchor || !isset($anchors[$fragment->anchor])) {
            return null;
        }
        $area = \Symfony\Component\HtmlRenderer\Css\PositionArea::parse($fragment->style['position-area'] ?? 'none', $fragment->style['direction'] ?? 'ltr', $fragment->style['writing-mode'] ?? 'horizontal-tb');
        $flips = [];
        foreach (Selector::splitTopLevel($fragment->style['position-try-fallbacks'] ?? \Symfony\Component\HtmlRenderer\Css\Anchor::order($fragment->style['position-try'] ?? '')[1]) as $fallback) {
            if (null !== $parsed = \Symfony\Component\HtmlRenderer\Css\Anchor::fallback($fallback)) {
                if (null === $parsed[0] && null === $parsed[2]) {
                    array_push($flips, ...$parsed[1]);
                }
            }
        }
        $edges = $this->anchorEdges($source, $fragment->anchor, $anchors);
        $center = ['anchor-center' === ($fragment->style['justify-self'] ?? ''), 'anchor-center' === ($fragment->style['align-self'] ?? '')];

        return AnchorGeometry::nextTo($anchors[$fragment->anchor], $fragment->bounds->width, $fragment->bounds->height, $this->viewportWidth, $this->viewportHeight, $area, $edges, $flips, $center, [$fragment->bounds->x, $fragment->bounds->y], fn (float $value, bool $vertical): float => $this->layoutMetrics?->constrainLength($value / 2, 'anchor-center-offset', $vertical) ?? $value / 2);
    }

    private function resolveAnchorLayout(Fragment $fragment, array $layer, \SplObjectStorage $anchors, bool $force = false): Fragment
    {
        [$box, $inherited] = $layer['source'];
        $style = $box->style;
        $resizeForArea = false;
        $reference = null !== $box->topLayer || 'fixed' === ($style['position'] ?? '') ? ($layer['fixed-containing'] ?? new Rect(0, 0, $this->viewportWidth, $this->viewportHeight)) : $layer['containing'];
        $lookup = static function (?string $name) use ($box, $anchors): ?Rect {
            $node = null === $name ? $box->anchor : ($box->anchorReferences[$name] ?? null);

            return null !== $node && isset($anchors[$node]) ? $anchors[$node] : null;
        };
        foreach (['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height'] as $property) {
            $value = trim($style[$property] ?? '');
            if (null === $parsed = \Symfony\Component\HtmlRenderer\Css\Anchor::sizeFunction($value, str_ends_with($property, 'height'))) {
                continue;
            }
            [$name, $dimension, $start, $end, $fallback] = $parsed;
            $anchor = $lookup($name);
            $replacement = null !== $anchor ? 'calc('.\sprintf('%.17H', $anchor->$dimension).')' : $fallback;
            $style[$property] = null === $replacement ? 'auto' : substr($value, 0, $start).$replacement.substr($value, $end + 1);
        }
        foreach (['top', 'right', 'bottom', 'left'] as $property) {
            if (null === $parsed = \Symfony\Component\HtmlRenderer\Css\Anchor::edge($style[$property] ?? '')) {
                continue;
            }
            [$name, $edge] = $parsed;
            if (null === $anchor = $lookup($name)) {
                $style[$property] = 'auto';
                continue;
            }
            $coordinate = match ($edge) {
                'left' => $anchor->x,
                'right' => $anchor->x + $anchor->width,
                'top' => $anchor->y,
                'bottom' => $anchor->y + $anchor->height,
                default => \in_array($property, ['left', 'right'], true) ? $anchor->x + $anchor->width / 2 : $anchor->y + $anchor->height / 2,
            };
            $offset = match ($property) {
                'left' => $coordinate - $reference->x,
                'right' => $reference->x + $reference->width - $coordinate,
                'top' => $coordinate - $reference->y,
                default => $reference->y + $reference->height - $coordinate,
            };
            $style[$property] = 'calc('.\sprintf('%.17H', $offset).')';
        }
        if (null !== $anchor = $lookup(null)) {
            if (null !== $area = \Symfony\Component\HtmlRenderer\Css\PositionArea::parse($style['position-area'] ?? 'none', $style['direction'] ?? 'ltr', $style['writing-mode'] ?? 'horizontal-tb')) {
                $reference = AnchorGeometry::area($anchor, $reference, $area);
                $resizeForArea = \in_array($style['width'] ?? 'auto', ['auto', 'min-content', 'max-content', 'fit-content'], true) || \in_array($style['height'] ?? 'auto', ['auto', 'min-content', 'max-content', 'fit-content'], true);
                foreach (['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height'] as $property) {
                    $resizeForArea = $resizeForArea || str_contains($style[$property] ?? '', '%');
                }
                foreach (['left', 'right', 'top', 'bottom'] as $property) {
                    if (isset($style[$property])) {
                        $style[$property] = 'auto';
                    }
                }
            }
        }
        if (!$force && !$resizeForArea && $style === $box->style) {
            return $fragment;
        }
        $box = $this->copiedBox($box, new Box($box->node, $box->display, $style, $box->children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries));
        $placeholder = new Fragment($box->node, $fragment->bounds);
        $this->positionedBoxes[$placeholder] = [$box, $inherited];

        try {
            $result = $this->position($placeholder, $layer['containing'], null, $layer['matrix'], $layer['fixed-containing'], $reference);
        } finally {
            unset($this->positionedBoxes[$placeholder]);
        }
        $this->anchorSources[$result] = $layer['source'];

        return $result;
    }

    private function union(Rect $first, Rect $second): Rect
    {
        $x = min($first->x, $second->x);
        $y = min($first->y, $second->y);

        return new Rect($x, $y, max($first->x + $first->width, $second->x + $second->width) - $x, max($first->y + $first->height, $second->y + $second->height) - $y);
    }

    private function caretAffinity(array $fragments): array
    {
        $claimed = false;
        for ($index = \count($fragments) - 1; $index >= 0; --$index) {
            $fragment = $fragments[$index];
            $run = $fragment->run;
            $selection = $run?->selection;
            if (null === $selection || !$selection->caretVisible || $selection->focus < $run->start || $selection->focus > $run->end) {
                continue;
            }
            if (!$claimed) {
                $claimed = true;
                continue;
            }
            $selection = new TextSelection($selection->anchor, $selection->focus, false, $selection->foreground, $selection->background);
            $fragments[$index] = new Fragment($fragment->node, $fragment->bounds, $fragment->style, $fragment->text, $fragment->children, $fragment->image, $fragment->transform, $fragment->backgrounds, new TextRun($run->start, $run->end, $selection, $run->sourceOffsets, $run->caretPositions), $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
        }

        return $fragments;
    }

    private function overflowBounds(Fragment $fragment, bool $localFixed = false): ?Rect
    {
        if (null !== $fragment->topLayer || !$localFixed && 'fixed' === ($fragment->style['position'] ?? 'static')) {
            return null;
        }
        $bounds = $fragment->bounds;
        if (null !== $fragment->run && null !== $caret = CaretGeometry::bounds($fragment, $this->textMeasurer, $this->layoutMetrics)) {
            $bounds = $this->union($bounds, $caret);
        }
        $clipX = \in_array($fragment->style['overflow-x'] ?? $fragment->style['overflow'] ?? 'visible', ['auto', 'scroll', 'hidden', 'clip'], true);
        $clipY = \in_array($fragment->style['overflow-y'] ?? $fragment->style['overflow'] ?? 'visible', ['auto', 'scroll', 'hidden', 'clip'], true);
        foreach ($clipX && $clipY ? [] : $fragment->children as $child) {
            if (null !== $childBounds = $this->overflowBounds($child, $localFixed || Containment::fixed($fragment->style))) {
                $childBounds = new Rect($clipX ? $fragment->bounds->x : $childBounds->x, $clipY ? $fragment->bounds->y : $childBounds->y, $clipX ? $fragment->bounds->width : $childBounds->width, $clipY ? $fragment->bounds->height : $childBounds->height);
                $bounds = $this->union($bounds, $childBounds);
            }
        }
        if (null !== $matrix = $fragment->transform) {
            $bounds = $this->union($bounds, $this->transformedBounds($bounds, $matrix));
        }

        return $bounds;
    }

    private function scrollTranslate(Fragment $fragment, float $x, float $y, bool $localFixed = false): Fragment
    {
        if (null !== $fragment->topLayer || !$localFixed && 'fixed' === ($fragment->style['position'] ?? 'static')) {
            return $fragment;
        }
        $bounds = $fragment->bounds;

        $result = new Fragment($fragment->node, new Rect($bounds->x + $x, $bounds->y + $y, $bounds->width, $bounds->height), $fragment->style, $fragment->text, array_map(fn (Fragment $child): Fragment => $this->scrollTranslate($child, $x, $y, $localFixed || Containment::fixed($fragment->style)), $fragment->children), $fragment->image, null === $fragment->transform ? null : Transform::moved($fragment->transform, $x, $y), $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll?->moved($x, $y), $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
        if (isset($this->anchorSources[$fragment])) {
            $this->anchorSources[$result] = $this->anchorSources[$fragment];
        }

        return $result;
    }

    private function marginFormattingRoot(Box $box, array $style, FontStyle $font, float $width): bool
    {
        return $box === $this->rootBox || !\in_array($box->display, ['block', 'list-item'], true)
            || \in_array($style['position'] ?? 'static', ['absolute', 'fixed'], true) || null !== $box->topLayer
            || 'none' !== ($style['float'] ?? 'none') || Containment::layout($style)
            || \in_array($style['overflow-x'] ?? $style['overflow'] ?? 'visible', ['hidden', 'scroll', 'auto'], true)
            || \in_array($style['overflow-y'] ?? $style['overflow'] ?? 'visible', ['hidden', 'scroll', 'auto'], true)
            || (isset($style['columns']) || isset($style['column-count']) || isset($style['column-width'])) && null !== $this->columnSpecification($style, $width, $font);
    }

    private function marginWhitespace(Box $box): bool
    {
        return 'inline' === $box->display && ($box->node instanceof \Dom\Text || null === $box->node) && null !== $box->text && '' === trim($box->text) && !\in_array($box->style['white-space'] ?? 'normal', ['pre', 'pre-wrap', 'break-spaces'], true);
    }

    private function marginClearance(Box $parent, Box $child, float $width, FontStyle $font): bool
    {
        $clear = $child->style['clear'] ?? 'none';
        if ('none' === $clear) {
            return false;
        }
        foreach ($parent->children as $before) {
            if ($before === $child) {
                break;
            }
            $side = $before->style['float'] ?? 'none';
            if (\in_array($side, ['left', 'right'], true) && ('both' === $clear || $clear === $side) && 0.0 !== $this->length($before->style['height'] ?? 'auto', $width, $font, 'height', true)) {
                return true;
            }
        }

        return false;
    }

    private function collapsedMargins(Box $box, float $availableWidth, FontStyle $font, int $depth = 0, bool $independent = false, ?float $forcedWidth = null): array
    {
        $fontKey = $this->fontKey($font);
        $key = null === $fontKey ? null : serialize([pack('d', $availableWidth), $fontKey, $independent, null === $forcedWidth ? null : pack('d', $forcedWidth)]);
        $cached = null === $key ? [] : ($this->marginCache[$box] ?? []);
        if (null !== $key && isset($cached[$key])) {
            return $cached[$key];
        }
        $style = $box->style;
        $margin = $this->edges($style, 'margin', $availableWidth, $font);
        $top = MarginStrut::from($margin[0]);
        $bottom = MarginStrut::from($margin[2]);
        $info = ['margin' => $margin, 'top' => $top, 'bottom' => $bottom, 'through' => false, 'first' => [], 'last' => []];
        if ($independent || $depth >= 64 || $this->marginFormattingRoot($box, $style, $font, $availableWidth)) {
            return $info;
        }
        $padding = $this->padding($box, $style, $availableWidth, $font);
        $border = $this->borders($style, $availableWidth, $font);
        $horizontal = $padding[1] + $padding[3] + $border[1] + $border[3];
        $specified = $this->length($style['width'] ?? 'auto', $availableWidth, $font, 'width');
        $outerWidth = $forcedWidth ?? (null === $specified ? max(0.0, $availableWidth - $margin[1] - $margin[3]) : max(0.0, $specified + ('border-box' === ($style['box-sizing'] ?? '') ? 0.0 : $horizontal)));
        if (null === $forcedWidth && null !== $intrinsic = $this->intrinsicKeywordWidth($box, $font, $style['width'] ?? 'auto', max(0.0, $availableWidth - $margin[1] - $margin[3] - $horizontal), $availableWidth, $horizontal)) {
            $outerWidth = $intrinsic + $horizontal;
        }
        $width = max(0.0, $this->constrain($outerWidth, $style, 'width', $availableWidth, $font, $horizontal) - $horizontal);
        $inFlow = array_values(array_filter($box->children, fn (Box $child): bool => 'none' !== $child->display && !$this->marginWhitespace($child) && null === $child->topLayer && 'none' === ($child->style['float'] ?? 'none') && !\in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)));
        $ownText = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : '');
        $hasText = null !== $box->replacedContent || null !== $box->image || '' !== trim($ownText) || \in_array($style['white-space'] ?? 'normal', ['pre', 'pre-wrap', 'break-spaces'], true) && '' !== $ownText;
        $minHeight = $this->length($style['min-height'] ?? '0', 0.0, $font, 'min-height', true) ?? 0.0;
        $height = $this->length($style['height'] ?? 'auto', 0.0, $font, 'height', true);
        $empty = !$hasText && (null === $height || 0.0 === $height) && 0.0 === $minHeight && 0.0 === $padding[0] + $padding[2] + $border[0] + $border[2];
        $children = [];
        foreach ($inFlow as $child) {
            if (!\in_array($child->display, ['block', 'flow-root', 'list-item'], true)) {
                $empty = false;
                $children[] = null;
                continue;
            }
            $childInfo = $this->collapsedMargins($child, $width, $this->font($child->style, ['font-family' => $font->family, 'font-size' => $font->size.'px', 'line-height' => $font->lineHeight.'px', 'font-weight' => (string) $font->weight, 'direction' => $font->direction, 'font-style' => $font->style, 'letter-spacing' => $font->letterSpacing.'px', 'word-spacing' => $font->wordSpacing.'px']), $depth + 1);
            $children[] = $childInfo;
            $empty = $empty && $childInfo['through'];
        }
        if (!$hasText && 0.0 === $padding[0] + $border[0]) {
            foreach ($inFlow as $index => $child) {
                if (null === $children[$index] || $this->marginClearance($box, $child, $width, $font)) {
                    break;
                }
                $top = $top->merge($children[$index]['top']);
                $info['first'][] = $child;
                if (!$children[$index]['through']) {
                    break;
                }
            }
        }
        if (!$hasText && null === $height && 0.0 === $minHeight && 0.0 === $padding[2] + $border[2]) {
            for ($index = \count($inFlow) - 1; $index >= 0; --$index) {
                if (null === $children[$index]) {
                    break;
                }
                $bottom = $bottom->merge($children[$index]['bottom']);
                $info['last'][] = $inFlow[$index];
                if (!$children[$index]['through']) {
                    break;
                }
            }
        }
        if ($empty) {
            $top = $bottom = $top->merge($bottom);
        }
        $info['through'] = $empty;
        $info['top'] = $top;
        $info['bottom'] = $bottom;
        $info['margin'][0] = $top->value();
        $info['margin'][2] = $bottom->value();
        if (null !== $key) {
            $cached[$key] = $info;
            $this->marginCache[$box] = $cached;
        }

        return $info;
    }

    private function containedSize(array $style, bool $vertical, float $base, FontStyle $font): ?float
    {
        if (!Containment::axes($style)[$vertical ? 1 : 0]) {
            return null;
        }
        $size = Containment::intrinsicSize($style)[$vertical ? 1 : 0];

        return null === $size ? 0.0 : max(0.0, $this->length($size, $base, $font, $vertical ? 'contain-intrinsic-height' : 'contain-intrinsic-width', $vertical) ?? 0.0);
    }

    private function columnSpecification(array $style, float $width, FontStyle $font): ?array
    {
        $count = $style['column-count'] ?? 'auto';
        $columnWidth = $style['column-width'] ?? 'auto';
        foreach (\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['columns'] ?? '') as $part) {
            if (ctype_digit($part)) {
                $count = $style['column-count'] ?? $part;
            } elseif ('auto' !== $part) {
                $columnWidth = $style['column-width'] ?? $part;
            }
        }
        $count = ctype_digit((string) $count) && (int) $count > 0 ? min(100, (int) $count) : null;
        $columnWidth = $this->length($columnWidth, $width, $font, 'column-width');
        $columnWidth = null !== $columnWidth && $columnWidth > 0 ? $columnWidth : null;
        if (null === $count && null === $columnWidth) {
            return null;
        }
        $gap = $style['column-gap'] ?? 'normal';
        $gap = 'normal' === $gap ? $font->size : ($this->length($gap, $width, $font, 'column-gap') ?? 0.0);
        [$count, $columnWidth] = ColumnGeometry::resolve($width, $count, $columnWidth, max(0.0, $gap));
        $columnWidth = $this->layoutMetrics?->constrainLength($columnWidth, 'column-width', false) ?? $columnWidth;

        return [$count, max(0.0, $columnWidth), max(0.0, $gap)];
    }

    private function columns(array $boxes, float $x, float $y, float $width, float $height, array $style, FontStyle $font, float $verticalChrome, array $specification, float $gutterBlock = 0.0): array
    {
        [$count, $columnWidth, $gap] = $specification;
        $children = [];
        $offset = 0.0;
        foreach ($this->columnSegments($boxes) as [$spans, $segment]) {
            if ($spans) {
                $fragment = $this->place(new Box(null, 'flow-root', [], $segment), $x, $y + $offset, $width, $height, $style, $width);
                array_push($children, ...$fragment->children);
                $offset += $fragment->bounds->height;
                continue;
            }
            $fragment = $this->place(new Box(null, 'flow-root', [], $segment), 0.0, 0.0, $columnWidth, $height, $style, $columnWidth);
            $total = $fragment->bounds->height;
            $breaks = [0.0, $total];
            $forced = $blocked = $forbidden = [];
            $this->columnBreaks($fragment, $breaks, $forced, $blocked, $forbidden);
            $breaks = array_values(array_filter($breaks, static fn (float $at): bool => !array_any($blocked, static fn (array $range): bool => $at > $range[0] + 1e-9 && $at < $range[1] - 1e-9) && !array_any($forbidden, static fn (float $point): bool => abs($point - $at) < 1e-9)));
            $fill = 'auto' === ($style['column-fill'] ?? 'balance') ? $this->length($style['height'] ?? $style['max-height'] ?? 'auto', $height, $font, 'height', true) : null;
            if (null !== $fill) {
                $fill = max(0.0, $fill - ('border-box' === ($style['box-sizing'] ?? '') ? $verticalChrome : $gutterBlock));
            }
            $parts = ColumnGeometry::partition($total, $count, $breaks, $forced, $fill);
            $limit = $this->length($style['height'] ?? $style['max-height'] ?? 'auto', $height, $font, 'height', true);
            if (null !== $limit) {
                $limit = max(0.0, $limit - ('border-box' === ($style['box-sizing'] ?? '') ? $verticalChrome : $gutterBlock));
            }
            if (null !== $limit && $limit > 0 && array_any($parts, static fn (array $part): bool => $part[1] - $part[0] > $limit + 1e-9)) {
                $parts = ColumnGeometry::partition($total, $count, $breaks, $forced, $limit, true);
            }
            $usedHeight = $parts ? max(array_map(static fn (array $part): float => $part[1] - $part[0], $parts)) : 0.0;
            foreach ($parts as $column => [$from, $to]) {
                $physicalColumn = 'rtl' === ($style['direction'] ?? 'ltr') ? $count - 1 - $column : $column;
                $pieces = [];
                foreach ($fragment->children as $child) {
                    if (null !== $piece = $this->columnFragment($child, $from, $to)) {
                        $pieces[] = $this->translate($piece, $x + $physicalColumn * ($columnWidth + $gap), $y + $offset - $from);
                    }
                }
                $children[] = new Fragment(null, new Rect($x + $physicalColumn * ($columnWidth + $gap), $y + $offset, $columnWidth, $usedHeight), ['display' => 'block', 'overflow' => 'hidden'], null, $pieces);
            }
            if (\count($parts) > 1 && $gap > 0 && null !== $rule = $this->columnRule($style, $width, $font)) {
                [$ruleWidth, $ruleStyle, $ruleColor] = $rule;
                for ($column = 1; $column < \count($parts); ++$column) {
                    $edge = 'rtl' === ($style['direction'] ?? 'ltr') ? $count - $column : $column;
                    $ruleX = $x + $edge * $columnWidth + ($edge - .5) * $gap - $ruleWidth / 2;
                    $children[] = new Fragment(null, new Rect($ruleX, $y + $offset, $ruleWidth, $usedHeight), ['border-left-width' => $ruleWidth.'px', 'border-left-style' => $ruleStyle, 'border-left-color' => $ruleColor]);
                }
            }
            $offset += $usedHeight;
        }

        return [$children, $offset];
    }

    private function columnRule(array $style, float $width, FontStyle $font): ?array
    {
        $kind = $style['column-rule-style'] ?? 'none';
        $size = $style['column-rule-width'] ?? 'medium';
        $color = $style['column-rule-color'] ?? $style['color'] ?? 'black';
        $scheme = ColorScheme::forced($style['color-scheme'] ?? '') ?? $this->preferredColorScheme;
        $alias = fn (string $value) => $this->colorAlias($value, $scheme);
        foreach (\Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['column-rule'] ?? '') as $part) {
            if (\in_array($part, ['none', 'hidden', 'solid', 'double', 'dashed', 'dotted', 'groove', 'ridge', 'inset', 'outset'], true)) {
                $kind = $style['column-rule-style'] ?? $part;
            } elseif (\is_array(ColorValue::parse($part, $scheme, $alias))) {
                $color = $style['column-rule-color'] ?? $part;
            } else {
                $size = $style['column-rule-width'] ?? $part;
            }
        }
        $size = $this->length(match ($size) {
            'thin' => '1px', 'medium' => '3px', 'thick' => '5px', default => $size,
        }, $width, $font, 'column-rule-width') ?? 0.0;
        if (\in_array($kind, ['none', 'hidden'], true) || $size <= 0) {
            return null;
        }
        $color = 'currentcolor' === strtolower($color) ? ($style['color'] ?? 'black') : $color;
        $resolved = ColorValue::parse($color, $scheme, $alias);

        return [$size, $kind, \is_array($resolved) ? ColorValue::stringify($resolved) : 'black'];
    }

    private function columnSegments(array $boxes, int $depth = 0): array
    {
        $segments = [];
        $run = [];
        foreach ($boxes as $box) {
            $inner = null;
            if ('all' === ($box->style['column-span'] ?? 'none')) {
                $inner = [[true, [$box]]];
            } elseif ($depth < 8 && 'block' === $box->display && $box->children && 'static' === ($box->style['position'] ?? 'static') && 'none' === ($box->style['float'] ?? 'none') && !isset($box->style['height']) && !isset($box->style['width']) && null === $this->columnSpecification($box->style, 0.0, $this->font($box->style, []))) {
                $nested = $this->columnSegments($box->children, $depth + 1);
                if (array_any($nested, static fn (array $part): bool => $part[0])) {
                    $inner = array_map(fn (array $part): array => [$part[0], [$this->copiedBox($box, new Box($box->node, $box->display, $box->style, $part[1], $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries))]], $nested);
                }
            }
            if (null === $inner) {
                $run[] = $box;
                continue;
            }
            if ($run) {
                $segments[] = [false, $run];
                $run = [];
            }
            array_push($segments, ...$inner);
        }
        if ($run) {
            $segments[] = [false, $run];
        }

        return $segments;
    }

    private function columnBreaks(Fragment $fragment, array &$breaks, array &$forced, array &$blocked, array &$forbidden): void
    {
        $top = $fragment->bounds->y;
        $bottom = $top + $fragment->bounds->height;
        $breaks[] = $bottom;
        $style = $fragment->style;
        if (\in_array($style['break-before'] ?? 'auto', ['column', 'always'], true)) {
            $forced[] = $top;
        }
        if (\in_array($style['break-after'] ?? 'auto', ['column', 'always'], true)) {
            $forced[] = $bottom;
        }
        if (null !== $fragment->content || \in_array($style['break-inside'] ?? 'auto', ['avoid', 'avoid-column'], true) || \in_array($style['display'] ?? '', ['inline-block', 'inline-flex', 'inline-grid', 'flex', 'grid'], true)) {
            $blocked[] = [$top, $bottom];
        }
        if ('block' === ($style['display'] ?? '')) {
            $lines = [];
            $collect = static function (Fragment $part) use (&$collect, &$lines): void {
                if (null !== $part->text) {
                    $lines[] = [$part->bounds->y, $part->bounds->y + $part->bounds->height];
                }
                foreach ($part->children as $child) {
                    if ('block' !== ($child->style['display'] ?? 'inline')) {
                        $collect($child);
                    }
                }
            };
            $collect($fragment);
            $ends = array_values(array_unique(array_column($lines, 1)));
            sort($ends, \SORT_NUMERIC);
            foreach ($ends as $index => $end) {
                if ($index < \count($ends) - 1 && ($index + 1 < (int) ($style['orphans'] ?? 2) || \count($ends) - $index - 1 < (int) ($style['widows'] ?? 2))) {
                    $forbidden[] = $end;
                }
            }
        }
        foreach ($fragment->children as $child) {
            $this->columnBreaks($child, $breaks, $forced, $blocked, $forbidden);
        }
    }

    private function columnFragment(Fragment $fragment, float $from, float $to): ?Fragment
    {
        $bounds = $fragment->bounds;
        if (null !== $fragment->topLayer || isset($this->positionedBoxes[$fragment])) {
            return $from <= $bounds->y && $bounds->y < $to ? $fragment : null;
        }
        $top = max($from, $bounds->y);
        $bottom = min($to, $bounds->y + $bounds->height);
        if ($bottom <= $top) {
            return null;
        }
        $children = [];
        foreach ($fragment->children as $child) {
            if (null !== $part = $this->columnFragment($child, $from, $to)) {
                $children[] = $part;
            }
        }
        $style = $fragment->style;
        if ($top > $bounds->y) {
            $style['border-top-width'] = '0';
        }
        if ($bottom < $bounds->y + $bounds->height) {
            $style['border-bottom-width'] = '0';
        }

        return new Fragment($fragment->node, new Rect($bounds->x, $top, $bounds->width, $bottom - $top), $style, $fragment->text, $children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
    }

    private function grid(Box $owner, array $boxes, float $x, float $y, float $width, float $height, array $style, FontStyle $font, float $verticalChrome, float $gutterBlock = 0.0, ?float $usedContentHeight = null): array
    {
        if (!$boxes) {
            return [[], 0.0];
        }
        usort($boxes, static fn (Box $first, Box $second): int => (int) ($first->style['order'] ?? 0) <=> (int) ($second->style['order'] ?? 0));
        $gaps = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['gap'] ?? '0');
        $rowGap = max(0.0, $this->length($style['row-gap'] ?? $gaps[0] ?? '0', $width, $font, 'row-gap', true) ?? 0.0);
        $columnGap = max(0.0, $this->length($style['column-gap'] ?? $gaps[1] ?? $gaps[0] ?? '0', $width, $font, 'column-gap') ?? 0.0);
        $inheritedTracks = $this->subgridTracks[$owner] ?? null;
        if (isset($inheritedTracks['columns']) && !isset($style['column-gap']) && !isset($style['gap'])) {
            $columnGap = $inheritedTracks['column-gap'];
        }
        $length = fn (string $value): ?float => $this->length($value, $width, $font, 'grid-template-columns');
        $areas = GridGeometry::areas($style['grid-template-areas'] ?? 'none') ?? [];
        $areaColumns = $areas ? max(array_column($areas, 3)) + 1 : 1;
        $areaRows = $areas ? max(array_column($areas, 2)) + 1 : 1;
        $tracks = GridGeometry::tracks($style['grid-template-columns'] ?? implode(' ', array_fill(0, $areaColumns, 'auto')), $width, $columnGap, $length, \count($boxes), 0, $columnLines);
        $tracks = $tracks ?: GridGeometry::tracks('auto', $width, $columnGap, $length);
        if (isset($inheritedTracks['columns'])) {
            $tracks = array_map(static fn (float $size): array => ['kind' => 'fixed', 'size' => $size, 'minimum' => 0.0, 'stretch' => false], $inheritedTracks['columns']);
        }

        if (isset($inheritedTracks['row-span'])) {
            $rowGap = $inheritedTracks['row-gap'];
        }
        $rowLength = fn (string $value): ?float => $this->length($value, $height, $font, 'grid-template-rows', true);
        $rowTracks = GridGeometry::tracks($style['grid-template-rows'] ?? ($areas ? implode(' ', array_fill(0, $areaRows, 'auto')) : ''), $height, $rowGap, $rowLength, null, 0, $rowLines);
        if (isset($inheritedTracks['row-span'])) {
            $rowTracks = GridGeometry::tracks(implode(' ', array_fill(0, $inheritedTracks['row-span'], 'auto')), $height, $rowGap, $rowLength);
        }
        $placed = GridGeometry::place(GridGeometry::namedStyles(array_column($boxes, 'style'), $areas, $columnLines, $rowLines, \count($tracks), \count($rowTracks)), \count($tracks), str_contains($style['grid-auto-flow'] ?? '', 'dense'), str_contains($style['grid-auto-flow'] ?? '', 'column'), max(1, \count($rowTracks)), $columnLead, $rowLead, isset($inheritedTracks['columns']));
        $columnCount = max(\count($tracks) + $columnLead, ...array_map(static fn (array $item): int => $item[1] + $item[3], $placed));
        $rowCount = max(\count($rowTracks) + $rowLead, ...array_map(static fn (array $item): int => $item[0] + $item[2], $placed));
        $autoColumns = GridGeometry::tracks($style['grid-auto-columns'] ?? 'auto', $width, $columnGap, $length) ?: GridGeometry::tracks('auto', $width, $columnGap, $length);
        if ($columnLead) {
            $leading = [];
            for ($column = -$columnLead; $column < 0; ++$column) {
                $leading[] = $autoColumns[($column % \count($autoColumns) + \count($autoColumns)) % \count($autoColumns)];
            }
            $tracks = [...$leading, ...$tracks];
        }
        $explicitColumns = \count($tracks);
        for ($column = $explicitColumns; $column < $columnCount; ++$column) {
            $tracks[] = $autoColumns[($column - $explicitColumns) % \count($autoColumns)];
        }
        $autoRows = GridGeometry::tracks($style['grid-auto-rows'] ?? 'auto', $height, $rowGap, $rowLength) ?: GridGeometry::tracks('auto', $height, $rowGap, $rowLength);
        if ($rowLead) {
            $leading = [];
            for ($row = -$rowLead; $row < 0; ++$row) {
                $leading[] = $autoRows[($row % \count($autoRows) + \count($autoRows)) % \count($autoRows)];
            }
            $rowTracks = [...$leading, ...$rowTracks];
        }
        $explicitRows = \count($rowTracks);
        for ($row = $explicitRows; $row < $rowCount; ++$row) {
            $rowTracks[] = $autoRows[($row - $explicitRows) % \count($autoRows)];
        }
        $intrinsic = $minContent = [];
        foreach ($boxes as $index => $box) {
            $itemFont = $this->font($box->style, $style);
            $margin = $this->edges($box->style, 'margin', $width, $itemFont);
            $padding = $this->padding($box, $box->style, $width, $itemFont);
            $border = $this->borders($box->style, $width, $itemFont);
            $intrinsic[$index] = $this->inlineOuterWidth($box, $width, $itemFont);
            $minContent[$index] = $this->minimumWidth($box, $itemFont) + $margin[1] + $margin[3] + $padding[1] + $padding[3] + $border[1] + $border[3];
        }
        $content = $minimum = array_fill(0, $columnCount, 0.0);
        foreach ($placed as $index => [, $column, , $span]) {
            if ($this->isColumnSubgrid($boxes[$index])) {
                foreach ($this->subgridContributions($boxes[$index], $span, $width, $style) as $offset => [$preferred, $least]) {
                    $content[$column + $offset] = max($content[$column + $offset], $preferred);
                    $minimum[$column + $offset] = max($minimum[$column + $offset], $least);
                }
                continue;
            }
            if (1 === $span) {
                $content[$column] = max($content[$column], $intrinsic[$index]);
                $minimum[$column] = max($minimum[$column], $minContent[$index]);
            }
        }
        $spanning = array_keys($placed);
        usort($spanning, static fn (int $first, int $second): int => $placed[$first][3] <=> $placed[$second][3]);
        foreach ($spanning as $index) {
            [, $column, , $span] = $placed[$index];
            if ($span < 2 || $this->isColumnSubgrid($boxes[$index]) || array_filter(\array_slice($tracks, $column, $span), static fn (array $track): bool => 'auto' !== $track['kind'])) {
                continue;
            }
            $missing = $intrinsic[$index] - array_sum(\array_slice($content, $column, $span)) - $columnGap * ($span - 1);
            if ($missing > 0) {
                for ($i = 0; $i < $span; ++$i) {
                    $content[$column + $i] += $missing / $span;
                }
            }
        }
        $widths = $this->distribute(GridGeometry::resolve($tracks, $width, $columnGap, $content, $minimum, \in_array($style['justify-content'] ?? 'normal', ['normal', 'stretch'], true)), false);
        $measured = $rowMetrics = $itemMargins = $itemWidths = [];
        foreach ($placed as $index => [$row, $column, $rowSpan, $columnSpan]) {
            $box = $boxes[$index];
            $areaWidth = array_sum(\array_slice($widths, $column, $columnSpan)) + $columnGap * ($columnSpan - 1);
            $itemFont = $this->font($box->style, $style);
            $margin = $itemMargins[$index] = $this->edges($box->style, 'margin', $width, $itemFont);
            if ($this->isColumnSubgrid($box)) {
                $this->subgridTracks[$box] = ['columns' => \array_slice($widths, $column, $columnSpan), 'column-gap' => $columnGap];
            }
            if ($this->isRowSubgrid($box)) {
                $this->subgridTracks[$box] = ($this->subgridTracks[$box] ?? []) + ['row-span' => $rowSpan, 'row-gap' => $rowGap, 'rows' => []];
                if ($row + $rowSpan <= \count($inheritedTracks['rows'] ?? [])) {
                    $tracks = $this->subgridTracks[$box];
                    $tracks['rows'] = \array_slice($inheritedTracks['rows'], $row, $rowSpan);
                    $this->subgridTracks[$box] = $tracks;
                }
            }
            $availableItemWidth = max(0.0, $areaWidth - $margin[1] - $margin[3]);
            $justify = $box->style['justify-self'] ?? $style['justify-items'] ?? 'stretch';
            $forced = 'auto' === ($box->style['width'] ?? 'auto') ? (\in_array($justify, ['normal', 'stretch'], true) ? $availableItemWidth : max(0.0, min($areaWidth, $this->inlineOuterWidth($box, $width, $itemFont)) - $margin[1] - $margin[3])) : null;
            $itemWidths[$index] = $forced;
            $measured[$index] = $this->place($box, 0.0, 0.0, $areaWidth, $height, $style, $forced);
            $needed = ($this->subgridTracks[$box] ?? [])['needed'] ?? null;
            if (null !== $needed && \count($needed) <= $rowSpan) {
                foreach ($needed as $offset => $neededHeight) {
                    $rowMetrics[] = ['row' => $row + $offset, 'span' => 1, 'height' => $neededHeight];
                }
            }
            $naturalHeight = null !== $needed && \count($needed) <= $rowSpan ? array_sum($needed) + $rowGap * ($rowSpan - 1) : $measured[$index]->bounds->height;
            $rowMetrics[] = ['row' => $row, 'span' => $rowSpan, 'height' => max(0.0, $naturalHeight + $margin[0] + $margin[2])];
        }
        $rowContent = TableGeometry::rows($rowMetrics, $rowCount, $rowGap);
        $definiteHeight = $usedContentHeight ?? $this->length($style['height'] ?? 'auto', $height, $font, 'height', true);
        if (null === $usedContentHeight && null !== $definiteHeight) {
            $definiteHeight = max(0.0, $definiteHeight - ('border-box' === ($style['box-sizing'] ?? '') ? $verticalChrome : $gutterBlock));
        }
        if (null !== $definiteHeight) {
            $heights = GridGeometry::resolve($rowTracks, $definiteHeight, $rowGap, $rowContent, $rowContent, \in_array($style['align-content'] ?? 'normal', ['normal', 'stretch'], true));
        } else {
            $unit = 0.0;
            foreach ($rowTracks as $index => $track) {
                if ('fr' === $track['kind'] && $track['size'] > 0) {
                    $unit = max($unit, $rowContent[$index] / $track['size']);
                }
            }
            $heights = [];
            foreach ($rowTracks as $index => $track) {
                $heights[$index] = match ($track['kind']) {
                    'fixed' => max($track['minimum'], $track['size']),
                    'fr' => max($track['minimum'], $unit * $track['size']),
                    default => max($track['minimum'], $rowContent[$index]),
                };
            }
        }
        if (isset($inheritedTracks['row-span'])) {
            $padding = $this->padding($owner, $style, $width, $font);
            $border = $this->borders($style, $width, $font);
            $above = $padding[0] + $border[0];
            $below = $padding[2] + $border[2];
            $needed = $heights;
            if ($needed) {
                $needed[0] += $above;
                $needed[array_key_last($needed)] += $below;
            }
            $this->subgridTracks[$owner] = ($this->subgridTracks[$owner] ?? []) + ['needed' => $needed];
            if ($rowCount <= \count($inheritedTracks['rows'])) {
                foreach ($inheritedTracks['rows'] as $row => $size) {
                    $heights[$row] = max($heights[$row], $size - (0 === $row ? $above : 0.0) - ($row === $rowCount - 1 ? $below : 0.0));
                }
            }
        }
        $heights = $this->distribute($heights, true);
        $contentHeight = array_sum($heights) + $rowGap * max(0, $rowCount - 1);
        $columnSpaces = FlexGeometry::spaces($columnCount, $width - array_sum($widths) - $columnGap * max(0, $columnCount - 1), $columnGap, $style['justify-content'] ?? 'normal');
        $rowSpaces = FlexGeometry::spaces($rowCount, ($definiteHeight ?? $contentHeight) - $contentHeight, $rowGap, $style['align-content'] ?? 'normal');
        $columnOffsets = $rowOffsets = [];
        $offset = 0.0;
        foreach ($widths as $index => $trackWidth) {
            $columnOffsets[$index] = $offset += $columnSpaces[$index];
            $offset += $trackWidth;
        }
        $offset = 0.0;
        foreach ($heights as $index => $trackHeight) {
            $rowOffsets[$index] = $offset += $rowSpaces[$index];
            $offset += $trackHeight;
        }
        $children = [];
        foreach ($placed as $index => [$row, $column, $rowSpan, $columnSpan]) {
            $fragment = $measured[$index];
            $margin = $itemMargins[$index];
            $areaWidth = array_sum(\array_slice($widths, $column, $columnSpan)) + array_sum(\array_slice($columnSpaces, $column + 1, $columnSpan - 1));
            $areaHeight = array_sum(\array_slice($heights, $row, $rowSpan)) + array_sum(\array_slice($rowSpaces, $row + 1, $rowSpan - 1));
            if ($this->isRowSubgrid($boxes[$index]) && \count(($this->subgridTracks[$boxes[$index]] ?? [])['needed'] ?? []) <= $rowSpan) {
                $tracks = $this->subgridTracks[$boxes[$index]];
                $given = \array_slice($heights, $row, $rowSpan);
                if ($tracks['rows'] !== $given && ($tracks['needed'] ?? []) !== $given) {
                    $tracks['rows'] = $given;
                    $this->subgridTracks[$boxes[$index]] = $tracks;
                    $fragment = $this->place($boxes[$index], 0.0, 0.0, $areaWidth, $height, $style, $itemWidths[$index]);
                }
            }
            $justify = $boxes[$index]->style['justify-self'] ?? $style['justify-items'] ?? 'stretch';
            $align = $boxes[$index]->style['align-self'] ?? $style['align-items'] ?? 'stretch';
            $dx = match ($justify) {
                'center' => ($areaWidth - $fragment->bounds->width - $margin[1] - $margin[3]) / 2,
                'end', 'right', 'flex-end' => $areaWidth - $fragment->bounds->width - $margin[1] - $margin[3],
                default => 0.0,
            };
            $dy = match ($align) {
                'center' => ($areaHeight - $fragment->bounds->height - $margin[0] - $margin[2]) / 2,
                'end', 'flex-end' => $areaHeight - $fragment->bounds->height - $margin[0] - $margin[2],
                default => 0.0,
            };
            if (\in_array($align, ['normal', 'stretch'], true) && 'auto' === ($boxes[$index]->style['height'] ?? 'auto')) {
                $fragment = new Fragment($fragment->node, new Rect($fragment->bounds->x, $fragment->bounds->y, $fragment->bounds->width, max(0.0, $areaHeight - $margin[0] - $margin[2])), $fragment->style, $fragment->text, $fragment->children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
            }
            $children[] = $this->translate($fragment, $x + $columnOffsets[$column] + $dx, $y + $rowOffsets[$row] + $dy);
        }

        return [$children, $definiteHeight ?? $contentHeight];
    }

    private function isRowSubgrid(Box $box): bool
    {
        return \in_array($box->display, ['grid', 'inline-grid'], true) && 'subgrid' === trim($box->style['grid-template-rows'] ?? '');
    }

    private function isColumnSubgrid(Box $box): bool
    {
        return \in_array($box->display, ['grid', 'inline-grid'], true) && 'subgrid' === trim($box->style['grid-template-columns'] ?? '');
    }

    /** @return array<int, array{float, float}> */
    private function subgridContributions(Box $box, int $span, float $width, array $inherited, int $depth = 0): array
    {
        $children = array_values(array_filter($box->children, static fn (Box $child): bool => 'none' !== $child->display && !\in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)));
        usort($children, static fn (Box $a, Box $b): int => (int) ($a->style['order'] ?? 0) <=> (int) ($b->style['order'] ?? 0));
        $placed = GridGeometry::place(array_column($children, 'style'), $span, str_contains($box->style['grid-auto-flow'] ?? '', 'dense'), str_contains($box->style['grid-auto-flow'] ?? '', 'column'), 1, $columnLead, $rowLead, true);
        $contributions = [];
        foreach ($placed as $index => [, $column, , $childSpan]) {
            $child = $children[$index];
            if ($depth < 8 && $this->isColumnSubgrid($child)) {
                foreach ($this->subgridContributions($child, $childSpan, $width, $box->style, $depth + 1) as $offset => [$preferred, $minimum]) {
                    $at = $column + $offset;
                    $contributions[$at] = [max($contributions[$at][0] ?? 0.0, $preferred), max($contributions[$at][1] ?? 0.0, $minimum)];
                }
            } elseif (1 === $childSpan) {
                $font = $this->font($child->style, $box->style ?: $inherited);
                $margin = $this->edges($child->style, 'margin', $width, $font);
                $padding = $this->padding($child, $child->style, $width, $font);
                $border = $this->borders($child->style, $width, $font);
                $minimum = $this->minimumWidth($child, $font) + $margin[1] + $margin[3] + $padding[1] + $padding[3] + $border[1] + $border[3];
                $contributions[$column] = [max($contributions[$column][0] ?? 0.0, $this->inlineOuterWidth($child, $width, $font)), max($contributions[$column][1] ?? 0.0, $minimum)];
            }
        }

        return array_filter($contributions, static fn (int $column): bool => $column < $span, \ARRAY_FILTER_USE_KEY);
    }

    private function flex(array $boxes, float $x, float $y, float $width, float $height, array $style, FontStyle $font, float $verticalChrome, float $gutterBlock = 0.0, ?float $usedContentHeight = null): array
    {
        $boxes = array_values(array_filter(array_map(function (Box $box): ?Box {
            if (($box->node instanceof \Dom\Text || null === $box->node && null === $box->replacedContent && null === $box->image && 'block' === $box->display && !array_any($box->children, static fn (Box $child): bool => 'inline' !== $child->display)) && \in_array($box->style['white-space'] ?? 'normal', ['normal', 'nowrap', 'pre-line'], true)) {
                $box = $this->trimBoundary($this->trimBoundary($box, false), true);
                if ('' === $box->text) {
                    return null;
                }
            }

            return $box;
        }, $boxes)));
        if (!$boxes) {
            return [[], 0.0];
        }
        usort($boxes, static fn (Box $first, Box $second): int => (int) ($first->style['order'] ?? 0) <=> (int) ($second->style['order'] ?? 0));
        $column = str_starts_with($style['flex-direction'] ?? 'row', 'column');
        $reverse = str_ends_with($style['flex-direction'] ?? 'row', 'reverse');
        $gaps = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['gap'] ?? '0');
        $rowGap = max(0.0, $this->length($style['row-gap'] ?? $gaps[0] ?? '0', $width, $font, 'row-gap', true) ?? 0.0);
        $columnGap = max(0.0, $this->length($style['column-gap'] ?? $gaps[1] ?? $gaps[0] ?? '0', $width, $font, 'column-gap') ?? 0.0);
        $gap = $column ? $rowGap : $columnGap;
        $crossGap = $column ? $columnGap : $rowGap;
        $definiteHeight = $usedContentHeight ?? $this->length($style['height'] ?? 'auto', $height, $font, 'height', true);
        if (null === $usedContentHeight && null !== $definiteHeight) {
            $definiteHeight = max(0.0, $definiteHeight - ('border-box' === ($style['box-sizing'] ?? '') ? $verticalChrome : $gutterBlock));
        }
        $mainRoom = $column ? $definiteHeight : $width;
        $metrics = $margins = $autoMargins = $crossWidths = [];
        foreach ($boxes as $index => $box) {
            $childFont = $this->font($box->style, $style);
            $margin = $margins[$index] = $this->edges($box->style, 'margin', $width, $childFont);
            $padding = $this->padding($box, $box->style, $width, $childFont);
            $borders = $this->borders($box->style, $width, $childFont);
            $chrome = $column ? $padding[0] + $padding[2] + $borders[0] + $borders[2] : $padding[1] + $padding[3] + $borders[1] + $borders[3];
            $around = $column ? $margin[0] + $margin[2] : $margin[1] + $margin[3];
            $flex = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($box->style['flex'] ?? '0 1 auto');
            if (['none'] === $flex) {
                $flex = ['0', '0', 'auto'];
            } elseif (['auto'] === $flex) {
                $flex = ['1', '1', 'auto'];
            }
            $grow = max(0.0, (float) ($box->style['flex-grow'] ?? $flex[0] ?? 0));
            $shrink = max(0.0, (float) ($box->style['flex-shrink'] ?? (isset($flex[1]) && is_numeric($flex[1]) ? $flex[1] : 1)));
            $basis = $box->style['flex-basis'] ?? $flex[2] ?? (isset($flex[1]) && !is_numeric($flex[1]) ? $flex[1] : '0%');
            $axis = $column ? 'height' : 'width';
            $basis = 'auto' === $basis ? ($box->style[$axis] ?? 'auto') : $basis;
            $alignment = $box->style['align-self'] ?? $style['align-items'] ?? 'stretch';
            $crossWidths[$index] = null;
            if ($column && !\in_array($alignment, ['normal', 'stretch'], true) && 'auto' === ($box->style['width'] ?? 'auto')) {
                $horizontalChrome = $padding[1] + $padding[3] + $borders[1] + $borders[3];
                $crossWidths[$index] = max($this->minimumWidth($box, $childFont) + $horizontalChrome, min(max(0.0, $width - $margin[1] - $margin[3]), $this->intrinsicWidth($box, $childFont) + $horizontalChrome));
            }
            $natural = $column ? $this->place($box, 0.0, 0.0, $width, $height, $style, $crossWidths[$index])->bounds->height : $this->intrinsicWidth($box, $childFont) + $chrome;
            $resolved = $this->length($basis, $mainRoom ?? $height, $childFont, 'flex-basis', $column);
            $base = null === $resolved ? $natural : $resolved + ('border-box' === ($box->style['box-sizing'] ?? '') ? 0.0 : $chrome);
            $minimum = $this->length($box->style['min-'.$axis] ?? 'auto', $mainRoom ?? $height, $childFont, 'min-'.$axis, $column);
            $maximum = $this->length($box->style['max-'.$axis] ?? 'none', $mainRoom ?? $height, $childFont, 'max-'.$axis, $column);
            $minContent = $column ? $natural : $this->minimumWidth($box, $childFont) + $chrome;
            $metrics[] = ['basis' => max(0.0, $base + $around), 'minimum' => max(0.0, null === $minimum ? min($base, $minContent) + $around : $minimum + $around + ('border-box' === ($box->style['box-sizing'] ?? '') ? 0.0 : $chrome)), 'maximum' => null === $maximum ? null : max(0.0, $maximum + $around + ('border-box' === ($box->style['box-sizing'] ?? '') ? 0.0 : $chrome)), 'grow' => $grow, 'shrink' => $shrink];
            $before = $column ? 'top' : 'left';
            $after = $column ? 'bottom' : 'right';
            $autoMargins[] = ['auto' === ($box->style['margin-'.$before] ?? $box->style['margin'] ?? '0'), 'auto' === ($box->style['margin-'.$after] ?? $box->style['margin'] ?? '0')];
        }
        $mainRoom ??= array_sum(array_column($metrics, 'basis')) + $gap * max(0, \count($boxes) - 1);
        $lines = [[]];
        $used = 0.0;
        foreach ($metrics as $index => $metric) {
            $hypothetical = max($metric['minimum'], min($metric['basis'], $metric['maximum'] ?? \INF));
            $last = array_key_last($lines);
            if ('nowrap' !== ($style['flex-wrap'] ?? 'nowrap') && $lines[$last] && $used + $gap + $hypothetical > $mainRoom) {
                $lines[] = [];
                $last = array_key_last($lines);
                $used = 0.0;
            }
            $used += ($lines[$last] ? $gap : 0.0) + $hypothetical;
            $lines[$last][] = $index;
        }
        $measured = $crossSizes = $mainSizes = [];
        foreach ($lines as $lineIndex => $line) {
            $sizes = $this->distribute(FlexGeometry::resolve(array_map(static fn (int $index): array => $metrics[$index], $line), $mainRoom, $gap), $column);
            $crossSizes[$lineIndex] = 0.0;
            foreach ($line as $offset => $index) {
                $margin = $margins[$index];
                $box = $boxes[$index];
                $childStyle = array_replace($box->style, ['margin' => '0', 'margin-top' => '0', 'margin-right' => '0', 'margin-bottom' => '0', 'margin-left' => '0']);
                $child = $this->copiedBox($box, new Box($box->node, $box->display, $childStyle, $box->children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries));
                if (isset($this->markerGutters[$box])) {
                    $this->markerGutters[$child] = $this->markerGutters[$box];
                }
                $fragment = $this->place($child, 0.0, 0.0, $width, $height, $style, $column ? $crossWidths[$index] : max(0.0, $sizes[$offset] - $margin[1] - $margin[3]), [], $column ? max(0.0, $sizes[$offset] - $margin[0] - $margin[2]) : null);
                $measured[$index] = $fragment;
                $mainSizes[$index] = $sizes[$offset];
                $crossSizes[$lineIndex] = max($crossSizes[$lineIndex], $column ? $fragment->bounds->width + $margin[1] + $margin[3] : $fragment->bounds->height + $margin[0] + $margin[2]);
            }
        }
        $naturalCross = array_sum($crossSizes) + $crossGap * max(0, \count($lines) - 1);
        $crossRoom = $column ? $width : ($definiteHeight ?? $naturalCross);
        $crossFree = max(0.0, $crossRoom - $naturalCross);
        if ('nowrap' === ($style['flex-wrap'] ?? 'nowrap') || \in_array($style['align-content'] ?? 'normal', ['normal', 'stretch'], true)) {
            foreach ($crossSizes as &$crossSize) {
                $crossSize += $crossFree / \count($lines);
            }
            unset($crossSize);
            $crossFree = 0.0;
        }
        $crossSizes = $this->distribute($crossSizes, !$column);
        $crossSpaces = FlexGeometry::spaces(\count($lines), $crossFree, $crossGap, $style['align-content'] ?? 'stretch');
        $children = [];
        $crossAt = 0.0;
        foreach ($lines as $lineIndex => $line) {
            $crossAt += $crossSpaces[$lineIndex];
            $lineCross = $crossSizes[$lineIndex];
            $crossStart = 'wrap-reverse' === ($style['flex-wrap'] ?? '') ? $crossRoom - $crossAt - $lineCross : $crossAt;
            $spaces = FlexGeometry::spaces(\count($line), $mainRoom - array_sum(array_map(static fn (int $index): float => $mainSizes[$index], $line)) - $gap * max(0, \count($line) - 1), $gap, $style['justify-content'] ?? 'flex-start', array_map(static fn (int $index): array => $autoMargins[$index], $line));
            $mainAt = 0.0;
            foreach ($line as $offset => $index) {
                $mainAt += $spaces[$offset];
                $margin = $margins[$index];
                $fragment = $measured[$index];
                $alignment = $boxes[$index]->style['align-self'] ?? $style['align-items'] ?? 'stretch';
                $crossDimension = $column ? $fragment->bounds->width : $fragment->bounds->height;
                $crossAround = $column ? $margin[1] + $margin[3] : $margin[0] + $margin[2];
                $slack = max(0.0, $lineCross - $crossDimension - $crossAround);
                if (\in_array($alignment, ['normal', 'stretch'], true) && 'auto' === ($boxes[$index]->style[$column ? 'width' : 'height'] ?? 'auto')) {
                    $fragment = new Fragment($fragment->node, new Rect(0.0, 0.0, $column ? max(0.0, $lineCross - $crossAround) : $fragment->bounds->width, $column ? $fragment->bounds->height : max(0.0, $lineCross - $crossAround)), $fragment->style, $fragment->text, $fragment->children, $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
                    $slack = 0.0;
                }
                $crossShift = match ($alignment) {
                    'center' => $slack / 2,
                    'end', 'flex-end' => $slack,
                    default => 0.0,
                };
                $main = $reverse ? $mainRoom - $mainAt - $mainSizes[$index] : $mainAt;
                $children[] = $this->translate($fragment, $x + ($column ? $crossStart + $crossShift : $main) + $margin[3], $y + ($column ? $main : $crossStart + $crossShift) + $margin[0]);
                $mainAt += $mainSizes[$index];
            }
            $crossAt += $lineCross;
        }

        return [$children, $column ? $mainRoom : $crossRoom];
    }

    private function distribute(array $lengths, bool $vertical): array
    {
        return $this->layoutMetrics?->distribute($lengths, $vertical) ?? $lengths;
    }

    private function constrain(float $size, array $style, string $axis, float $base, FontStyle $font, float $decoration): float
    {
        $chrome = 'border-box' === ($style['box-sizing'] ?? '') ? 0.0 : $decoration;
        $maximum = $this->length($style['max-'.$axis] ?? 'none', $base, $font, 'max-'.$axis, 'height' === $axis);
        $minimum = $this->length($style['min-'.$axis] ?? 'auto', $base, $font, 'min-'.$axis, 'height' === $axis);
        if (null !== $maximum && $maximum >= 0) {
            $size = min($size, $maximum + $chrome);
        }
        if (null !== $minimum && $minimum >= 0) {
            $size = max($size, $minimum + $chrome);
        }

        return max($decoration, $size);
    }

    private function isCaption(Box $box): bool
    {
        return 'table-caption' === $box->display;
    }

    private function captionedTable(Box $box, array $style, float $x, float $y, float $availableWidth, float $availableHeight, array $inherited, ?float $forcedWidth, ?float $forcedHeight, float $lengthWidth): Fragment
    {
        $font = $this->font($style, $inherited);
        $margin = $this->edges($style, 'margin', $lengthWidth, $font);
        $top = $bottom = $gridChildren = [];
        $minimum = 0.0;
        foreach ($box->children as $child) {
            if (!$this->isCaption($child)) {
                $gridChildren[] = $child;
                continue;
            }
            if ('none' === $child->display) {
                continue;
            }
            $captionFont = $this->font($child->style, $style);
            $padding = $this->padding($child, $child->style, $lengthWidth, $captionFont);
            $border = $this->borders($child->style, $lengthWidth, $captionFont);
            $captionMargin = $this->edges($child->style, 'margin', $lengthWidth, $captionFont);
            $minimum = max($minimum, $this->minimumWidth($child, $captionFont) + $padding[1] + $padding[3] + $border[1] + $border[3] + $captionMargin[1] + $captionMargin[3]);
            'bottom' === ($child->style['caption-side'] ?? $style['caption-side'] ?? 'top') ? $bottom[] = $child : $top[] = $child;
        }
        $gridStyle = array_replace($style, ['overflow' => 'visible', 'overflow-x' => 'visible', 'overflow-y' => 'visible', 'scrollbar-gutter' => 'auto', 'margin' => '0', 'margin-top' => '0', 'margin-right' => '0', 'margin-bottom' => '0', 'margin-left' => '0', 'position' => 'static', 'float' => 'none', 'transform' => 'none', 'translate' => 'none', 'rotate' => 'none', 'scale' => 'none', 'opacity' => '1', 'z-index' => 'auto']);
        unset($gridStyle['-renderer-scrollbar-key'], $gridStyle['--html-scroll-x'], $gridStyle['--html-scroll-y']);
        $gridBox = $this->copiedBox($box, new Box($box->node, $box->display, $gridStyle, $gridChildren, $box->text, $box->image, $box->selection, 'table-grid', null, $box->borderImage));
        $room = max(0.0, $availableWidth - $margin[1] - $margin[3]);
        $grid = $this->place($gridBox, 0.0, 0.0, $room, $availableHeight, $inherited, $forcedWidth, [], $forcedHeight, $lengthWidth);
        if ($grid->bounds->width < $minimum) {
            $padding = $this->padding($gridBox, $gridStyle, $lengthWidth, $font);
            $border = $this->borders($gridStyle, $lengthWidth, $font);
            $chrome = $padding[1] + $padding[3] + $border[1] + $border[3];
            $specified = max(0.0, $minimum - ('border-box' === ($gridStyle['box-sizing'] ?? '') ? 0.0 : $chrome));
            $gridStyle['width'] = $gridStyle['min-width'] = $specified.'px';
            $gridBox = $this->copiedBox($box, new Box($box->node, $box->display, $gridStyle, $gridChildren, $box->text, $box->image, $box->selection, 'table-grid', null, $box->borderImage));
            $grid = $this->place($gridBox, 0.0, 0.0, $room, $availableHeight, $inherited, null, [], $forcedHeight, $lengthWidth);
        }
        $x += $margin[3];
        $y += $margin[0];
        $offset = 0.0;
        $children = [];
        foreach ([$top, $bottom] as $side => $captions) {
            if (1 === $side) {
                $children[] = $this->translate($grid, $x, $y + $offset);
                $offset += $grid->bounds->height;
            }
            foreach ($captions as $caption) {
                $captionFont = $this->font($caption->style, $style);
                $captionMargin = $this->edges($caption->style, 'margin', $grid->bounds->width, $captionFont);
                $captionBox = $this->copiedBox($caption, new Box($caption->node, 'table-caption', $caption->style, $caption->children, $caption->text, $caption->image, $caption->selection, $caption->pseudoElement, $caption->topLayer, $caption->borderImage, $caption->anchor, $caption->replacedContent, $caption->anchorReferences, $caption->positionTries));
                $fragment = $this->place($captionBox, $x, $y + $offset, $grid->bounds->width, $availableHeight, $style);
                $children[] = $fragment;
                $offset += $captionMargin[0] + $fragment->bounds->height + $captionMargin[2];
            }
        }
        $wrapperStyle = [];
        foreach ($style as $property => $value) {
            if (!preg_match('/^(?:border|padding|background)(?:-|$)/', $property) && !\in_array($property, ['width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'box-shadow'], true)) {
                $wrapperStyle[$property] = $value;
            }
        }
        $wrapperStyle['display'] = 'inline-table' === $box->display ? 'inline-block' : 'flow-root';

        return new Fragment($box->node, new Rect($x, $y, $grid->bounds->width, max(0.0, $offset)), $wrapperStyle, null, $children, null, null, [], null, $box->pseudoElement, $box->topLayer, null, null, $box->anchor);
    }

    private function tableChildren(array $children): array
    {
        $out = $row = $cell = [];
        $flushCell = static function (array &$row, array &$cell): void {
            if ($cell) {
                $row[] = new Box(null, 'table-cell', [], $cell);
                $cell = [];
            }
        };
        $flushRow = static function (array &$out, array &$row, array &$cell) use ($flushCell): void {
            $flushCell($row, $cell);
            if ($row) {
                $out[] = new Box(null, 'table-row', [], $row);
                $row = [];
            }
        };
        foreach ($children as $child) {
            if ('none' === $child->display || $this->marginWhitespace($child) || null !== $child->topLayer || \in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)) {
                continue;
            }
            if (\in_array($child->display, ['table-row', 'table-row-group', 'table-header-group', 'table-footer-group', 'table-caption', 'table-column', 'table-column-group'], true)) {
                $flushRow($out, $row, $cell);
                $out[] = $child;
            } elseif ('table-cell' === $child->display) {
                $flushCell($row, $cell);
                $row[] = $child;
            } else {
                $cell[] = $child;
            }
        }
        $flushRow($out, $row, $cell);

        return $out;
    }

    /** @return list<float|null> */
    private function tableColumnWidths(Box $table, float $width): array
    {
        $widths = [];
        foreach ($table->children as $child) {
            if (!\in_array($child->display, ['table-column', 'table-column-group'], true)) {
                continue;
            }
            $columns = 'table-column-group' === $child->display ? array_filter($child->children, static fn (Box $column): bool => 'table-column' === $column->display) : [];
            foreach ($columns ?: [$child] as $column) {
                $span = $column->node instanceof \Dom\Element ? min(1000, max(1, (int) $column->node->getAttribute('span'))) : 1;
                $size = $this->length($column->style['width'] ?? 'auto', $width, $this->font($column->style, $table->style), 'width');
                array_push($widths, ...array_fill(0, min($span, 1000 - \count($widths)), $size));
            }
        }

        return $widths;
    }

    /** @return list<Fragment> */
    private function collapsedSegments(array $model, array $widths, array $heights, float $x, float $y): array
    {
        $columns = [0.0];
        foreach ($widths as $width) {
            $columns[] = $columns[array_key_last($columns)] + $width;
        }
        $rows = [0.0];
        foreach ($heights as $height) {
            $rows[] = $rows[array_key_last($rows)] + $height;
        }
        $joins = static fn (int $row, int $column): string => implode('', [($model['vertical'][$row - 1][$column]['width'] ?? 0) > 0 ? '1' : '0', ($model['horizontal'][$row][$column]['width'] ?? 0) > 0 ? '1' : '0', ($model['vertical'][$row][$column]['width'] ?? 0) > 0 ? '1' : '0', ($model['horizontal'][$row][$column - 1]['width'] ?? 0) > 0 ? '1' : '0']);
        $offset = static function (?array $first, ?array $second = null): float {
            $border = ($first['width'] ?? 0) >= ($second['width'] ?? 0) ? $first : $second;

            return null === $border ? 0.0 : ($border['halves'][1] - $border['halves'][0]) / 2;
        };
        $segments = [];
        foreach (['horizontal', 'vertical'] as $axis) {
            foreach ($model[$axis] as $row => $line) {
                foreach ($line as $column => $border) {
                    if ($border['width'] <= 0) {
                        continue;
                    }
                    $horizontal = 'horizontal' === $axis;
                    if ($horizontal) {
                        $start = $columns[$column] + $offset($model['vertical'][$row - 1][$column] ?? null, $model['vertical'][$row][$column] ?? null);
                        $end = $columns[$column + 1] + $offset($model['vertical'][$row - 1][$column + 1] ?? null, $model['vertical'][$row][$column + 1] ?? null);
                        $bounds = new Rect($x + $start, $y + $rows[$row] + $offset($border), max(0.0, $end - $start), 0.0);
                    } else {
                        $start = $rows[$row] + $offset($model['horizontal'][$row][$column - 1] ?? null, $model['horizontal'][$row][$column] ?? null);
                        $end = $rows[$row + 1] + $offset($model['horizontal'][$row + 1][$column - 1] ?? null, $model['horizontal'][$row + 1][$column] ?? null);
                        $bounds = new Rect($x + $columns[$column] + $offset($border), $y + $start, 0.0, max(0.0, $end - $start));
                    }
                    $segments[] = new Fragment(null, $bounds, ['color' => $border['color'], 'border-style' => match ($border['style']) {
                        'inset' => 'ridge', 'outset' => 'groove', default => $border['style'],
                    }, '-renderer-line-width' => (string) $border['width'], '-renderer-line-axis' => $horizontal ? 'x' : 'y', '-renderer-line-start' => $joins($row, $column), '-renderer-line-end' => $joins($row + ($horizontal ? 0 : 1), $column + ($horizontal ? 1 : 0))], null, [], null, null, [], null, 'table-border');
                }
            }
        }

        return $segments;
    }

    private function tablePartStyle(Box $box): array
    {
        if (!isset($this->collapsedWidths[$box])) {
            return $box->style;
        }
        $style = $box->style;
        $style['-renderer-collapsed-border'] = '1';
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $style['border-'.$side.'-width'] = '0px';
        }

        return $style;
    }

    private function collapsedTable(Box $table, array $style, float $width, FontStyle $font): array
    {
        $source = $this->boxOrigins[$table] ?? $table;
        if (isset($this->collapsedTables[$source])) {
            return $this->collapsedTables[$source];
        }
        $structure = $this->tableStructure($table);
        $columns = max($structure['columns'], \count($this->tableColumnWidths($table, $width)));
        $rows = \count($structure['rows']);
        if ($rows * $columns > self::MAX_COLLAPSED_POSITIONS) {
            throw new \LengthException('A collapsed table exceeds the border grid position limit.');
        }
        $this->tableStructures[$table] = $structure;
        $owners = $bounds = $sides = [];
        $candidates = new \SplObjectStorage();
        $register = function (Box $box, int $origin, int $row, int $column) use ($candidates, $width, $style): void {
            $cellStyle = $box->style;
            $font = $this->font($cellStyle, $style);
            $widths = $this->borders($cellStyle, $width, $font);
            $edges = [];
            $scheme = ColorScheme::forced($cellStyle['color-scheme'] ?? '') ?? $this->preferredColorScheme;
            foreach (['top', 'right', 'bottom', 'left'] as $side => $name) {
                $color = \Symfony\Component\HtmlRenderer\Css\CurrentColor::replace($cellStyle['border-'.$name.'-color'] ?? $cellStyle['border-color'] ?? 'currentcolor', $cellStyle['color'] ?? $style['color'] ?? 'black');
                $resolved = ColorValue::parse($color, $scheme, fn (string $value) => $this->colorAlias($value, $scheme));
                $color = \is_array($resolved) ? ColorValue::stringify($resolved) : 'black';
                $edges[] = ['width' => $widths[$side], 'style' => $cellStyle['border-'.$name.'-style'] ?? $cellStyle['border-style'] ?? 'solid', 'color' => $color, 'origin' => $origin, 'row' => $row, 'column' => $column];
            }
            $candidates[$box] = $edges;
        };
        $register($table, 0, 0, 0);
        $groupAt = [];
        $at = 0;
        foreach ($structure['groups'] as $group) {
            $count = \count($group['rows']);
            if (null !== $group['box']) {
                $register($group['box'], 3, $at, 0);
                $this->collapsedWidths[$group['box']] = [0.0, 0.0, 0.0, 0.0];
            }
            foreach ($group['rows'] as $offset => $row) {
                $register($row, 4, $at + $offset, 0);
                $this->collapsedWidths[$row] = [0.0, 0.0, 0.0, 0.0];
                $groupAt[$at + $offset] = [$group['box'], $at, $at + $count];
            }
            $at += $count;
        }
        foreach ($structure['cells'] as $index => $cell) {
            $register($cell['box'], 5, $cell['row'], $cell['column']);
            $bounds[] = [$cell['row'], $cell['column'], $cell['rowSpan'], $cell['span']];
            [$top, $right, $bottom, $left] = $candidates[$cell['box']];
            $sides[] = [$top['width'] > 0, $right['width'] > 0, $bottom['width'] > 0, $left['width'] > 0];
            for ($row = $cell['row']; $row < $cell['row'] + $cell['rowSpan']; ++$row) {
                for ($column = $cell['column']; $column < $cell['column'] + $cell['span']; ++$column) {
                    $owners[$row][$column] = $index;
                }
            }
        }
        $columnAt = [];
        $at = 0;
        foreach ($table->children as $columnGroup) {
            if (!\in_array($columnGroup->display, ['table-column', 'table-column-group'], true)) {
                continue;
            }
            $children = 'table-column-group' === $columnGroup->display ? array_filter($columnGroup->children, static fn (Box $box): bool => 'table-column' === $box->display) : [];
            $first = $at;
            foreach ($children ?: [$columnGroup] as $column) {
                $span = $column->node instanceof \Dom\Element ? min(1000, max(1, (int) $column->node->getAttribute('span'))) : 1;
                $register($column, 'table-column' === $column->display ? 2 : 1, 0, $at);
                for ($offset = 0; $offset < $span && $at + $offset < $columns; ++$offset) {
                    $columnAt[$at + $offset] = [$column, $at, min($columns, $at + $span), null, 0, 0];
                }
                $at += $span;
            }
            if ($children) {
                $register($columnGroup, 1, 0, $first);
                for ($column = $first; $column < min($at, $columns); ++$column) {
                    $columnAt[$column][3] = $columnGroup;
                    $columnAt[$column][4] = $first;
                    $columnAt[$column][5] = min($at, $columns);
                }
            }
        }
        $grid = new CollapsedBorderGrid($owners, $bounds, $sides, array_fill(0, $rows, [false, false, false, false]), [false, false, false, false], $columns);
        $edge = static fn (Box $box, int $side): array => $candidates[$box][$side];
        $cellEdge = static function (?int $row, int $column, int $side) use ($owners, $structure, $edge): array {
            if (null === $row || !isset($owners[$row][$column])) {
                return [];
            }
            $cell = $structure['cells'][$owners[$row][$column]];
            $onSide = match ($side) {
                0 => $row === $cell['row'],
                1 => $cell['column'] + $cell['span'] === $column + 1,
                2 => $cell['row'] + $cell['rowSpan'] === $row + 1,
                default => $column === $cell['column'],
            };

            return $onSide ? [$edge($cell['box'], $side)] : [];
        };
        $horizontal = $vertical = [];
        $rtl = 'rtl' === ($style['direction'] ?? 'ltr');
        for ($row = 0; $row <= $rows; ++$row) {
            for ($column = 0; $column < $columns; ++$column) {
                $above = $row > 0 ? $row - 1 : null;
                $below = $row < $rows ? $row : null;
                if (!$grid->horizontalBoundary($above, $below, $column)) {
                    continue;
                }
                $pool = [...$cellEdge($above, $column, 2), ...$cellEdge($below, $column, 0)];
                foreach ([[$above, 2], [$below, 0]] as [$index, $side]) {
                    if (null !== $index) {
                        $pool[] = $edge($structure['rows'][$index], $side);
                        [$group, $start, $end] = $groupAt[$index];
                        if (null !== $group && (0 === $side ? $row === $start : $row === $end)) {
                            $pool[] = $edge($group, $side);
                        }
                    }
                }
                if (0 === $row || $rows === $row) {
                    $side = 0 === $row ? 0 : 2;
                    $pool[] = $edge($table, $side);
                    if (isset($columnAt[$column])) {
                        $pool[] = $edge($columnAt[$column][0], $side);
                        if (null !== $columnAt[$column][3]) {
                            $pool[] = $edge($columnAt[$column][3], $side);
                        }
                    }
                }
                if (null !== $winner = CollapsedBorderGrid::winner($pool, $rtl)) {
                    $winner['halves'] = CollapsedBorderGrid::halves($winner['width'], true, $this->layoutMetrics);
                    $horizontal[$row][$column] = $winner;
                }
            }
        }
        for ($row = 0; $row < $rows; ++$row) {
            for ($column = 0; $column <= $columns; ++$column) {
                if (!$grid->verticalBoundary($row, $column)) {
                    continue;
                }
                $pool = [...$cellEdge($row, $column - 1, 1), ...$cellEdge($row, $column, 3)];
                foreach ([[$column - 1, 1], [$column, 3]] as [$index, $side]) {
                    if (isset($columnAt[$index])) {
                        [$col, $start, $end, $group, $groupStart, $groupEnd] = $columnAt[$index];
                        if (1 === $side ? $column === $end : $column === $start) {
                            $pool[] = $edge($col, $side);
                        }
                        if (null !== $group && (1 === $side ? $column === $groupEnd : $column === $groupStart)) {
                            $pool[] = $edge($group, $side);
                        }
                    }
                }
                if (0 === $column || $columns === $column) {
                    $side = 0 === $column ? 3 : 1;
                    $pool[] = $edge($table, $side);
                    $pool[] = $edge($structure['rows'][$row], $side);
                    if (null !== $groupAt[$row][0]) {
                        $pool[] = $edge($groupAt[$row][0], $side);
                    }
                }
                if (null !== $winner = CollapsedBorderGrid::winner($pool, $rtl)) {
                    $winner['halves'] = CollapsedBorderGrid::halves($winner['width'], false, $this->layoutMetrics);
                    $vertical[$row][$column] = $winner;
                }
            }
        }
        foreach ($structure['cells'] as $cell) {
            $used = [0.0, 0.0, 0.0, 0.0];
            for ($column = $cell['column']; $column < $cell['column'] + $cell['span']; ++$column) {
                $used[0] = max($used[0], $horizontal[$cell['row']][$column]['halves'][1] ?? 0.0);
                $used[2] = max($used[2], $horizontal[$cell['row'] + $cell['rowSpan']][$column]['halves'][0] ?? 0.0);
            }
            for ($row = $cell['row']; $row < $cell['row'] + $cell['rowSpan']; ++$row) {
                $used[3] = max($used[3], $vertical[$row][$cell['column']]['halves'][1] ?? 0.0);
                $used[1] = max($used[1], $vertical[$row][$cell['column'] + $cell['span']]['halves'][0] ?? 0.0);
            }
            $this->collapsedWidths[$cell['box']] = $used;
        }
        $outer = [0.0, 0.0, 0.0, 0.0];
        foreach ($horizontal[0] ?? [] as $border) {
            $outer[0] = max($outer[0], $border['halves'][0]);
        }
        foreach ($horizontal[$rows] ?? [] as $border) {
            $outer[2] = max($outer[2], $border['halves'][1]);
        }
        $outer[3] = $vertical[0][0]['halves'][0] ?? 0.0;
        $outer[1] = $vertical[0][$columns]['halves'][1] ?? 0.0;
        $this->collapsedWidths[$source] = $outer;

        return $this->collapsedTables[$source] = ['horizontal' => $horizontal, 'vertical' => $vertical];
    }

    /** @return array{groups: list<array{box: Box|null, rows: list<Box>}>, rows: list<Box>, cells: list<array{box: Box, column: int, span: int, row: int, rowSpan: int}>, columns: int} */
    private function tableStructure(Box $table): array
    {
        if (isset($this->tableStructures[$table])) {
            return $this->tableStructures[$table];
        }
        $groups = [];
        foreach ($this->tableChildren($table->children) as $child) {
            if ('none' === $child->display) {
                continue;
            }
            $tag = $child->node instanceof \Dom\Element ? strtolower($child->node->localName) : '';
            if ('table-row' === $child->display || 'tr' === $tag) {
                $last = array_key_last($groups);
                if (null === $last || null !== $groups[$last]['box']) {
                    $groups[] = ['box' => null, 'rows' => []];
                    $last = array_key_last($groups);
                }
                $groups[$last]['rows'][] = $child;
            } elseif (\in_array($child->display, ['table-row-group', 'table-header-group', 'table-footer-group'], true) || \in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                $rows = array_values(array_filter($child->children, static fn (Box $row): bool => 'none' !== $row->display && ('table-row' === $row->display || $row->node instanceof \Dom\Element && 'tr' === strtolower($row->node->localName))));
                $groups[] = ['box' => $child, 'rows' => $rows];
            }
        }
        $header = $footer = null;
        foreach ($groups as $index => $group) {
            $node = $group['box']?->node;
            $display = $group['box']?->display;
            if (null === $header && ('table-header-group' === $display || $node instanceof \Dom\Element && 'thead' === $node->localName)) {
                $header = $index;
            } elseif (null === $footer && ('table-footer-group' === $display || $node instanceof \Dom\Element && 'tfoot' === $node->localName)) {
                $footer = $index;
            }
        }
        $ordered = [];
        if (null !== $header) {
            $ordered[] = $groups[$header];
        }
        foreach ($groups as $index => $group) {
            if ($index !== $header && $index !== $footer) {
                $ordered[] = $group;
            }
        }
        if (null !== $footer) {
            $ordered[] = $groups[$footer];
        }
        $groups = $ordered;
        $rows = $ends = [];
        foreach ($groups as $group) {
            $end = \count($rows) + \count($group['rows']);
            foreach ($group['rows'] as $row) {
                $rows[] = $row;
                $ends[] = $end;
            }
        }
        if (!$rows) {
            return ['groups' => [], 'rows' => [], 'cells' => [], 'columns' => 0];
        }
        $cells = $taken = [];
        $columns = 0;
        foreach ($rows as $rowIndex => $row) {
            $column = 0;
            foreach ($row->children as $cell) {
                if ('none' === $cell->display) {
                    continue;
                }
                $node = $cell->node;
                $span = min(1000, max(1, $node instanceof \Dom\Element ? (int) $node->getAttribute('colspan') : 1));
                $available = 0;
                for ($probe = $column; $probe < 1000; ++$probe) {
                    if (isset($taken[$rowIndex][$probe])) {
                        $available = 0;
                        $column = $probe + 1;
                    } elseif (++$available >= $span) {
                        break;
                    }
                }
                if ($column >= 1000 || 0 === $available) {
                    break;
                }
                $span = min($span, $available);
                $rowSpan = $node instanceof \Dom\Element && $node->hasAttribute('rowspan') ? (int) $node->getAttribute('rowspan') : 1;
                $rowSpan = min(100, $ends[$rowIndex] - $rowIndex, 0 === $rowSpan ? $ends[$rowIndex] - $rowIndex : max(1, $rowSpan));
                if ('collapse' === ($table->style['border-collapse'] ?? 'separate') && \count($rows) * max($columns, $column + $span) > self::MAX_COLLAPSED_POSITIONS) {
                    throw new \LengthException('A collapsed table exceeds the border grid position limit.');
                }
                for ($below = 1; $below < $rowSpan; ++$below) {
                    for ($i = 0; $i < $span; ++$i) {
                        $taken[$rowIndex + $below][$column + $i] = true;
                    }
                }
                $cells[] = ['box' => $cell, 'column' => $column, 'span' => $span, 'row' => $rowIndex, 'rowSpan' => $rowSpan];
                $column += $span;
                $columns = max($columns, $column);
            }
        }

        return ['groups' => $groups, 'rows' => $rows, 'cells' => $cells, 'columns' => $columns];
    }

    private function table(Box $table, float $x, float $y, float $width, float $height, array $style, FontStyle $font): array
    {
        ['groups' => $groups, 'rows' => $rows, 'cells' => $cells, 'columns' => $columns] = $this->tableStructure($table);
        if (!$rows) {
            return [[], 0.0, 0.0];
        }
        $spacing = preg_split('/\s+/', trim($style['border-spacing'] ?? '0')) ?: ['0'];
        $gapX = max(0.0, $this->length($spacing[0], $width, $font, 'border-spacing') ?? 0.0);
        $gapY = max(0.0, $this->length($spacing[1] ?? $spacing[0], $width, $font, 'border-spacing', true) ?? 0.0);
        if ('collapse' === ($style['border-collapse'] ?? 'separate')) {
            $gapX = $gapY = 0.0;
        }
        $columnWidths = $this->tableColumnWidths($table, $width);
        $fixed = 'fixed' === ($style['table-layout'] ?? 'auto') && null !== $this->length($style['width'] ?? 'auto', $width, $font, 'width');
        $specified = [];
        $given = [];
        foreach ($columnWidths as $column => $size) {
            if (null !== $size) {
                $specified[$column] = true;
                $given[$column] = max(0.0, $size);
            }
        }
        $columns = max($columns, \count($columnWidths));
        $constraints = [];
        foreach ($cells as &$entry) {
            $cell = $entry['box'];
            $column = $entry['column'];
            $span = $entry['span'];
            $rowIndex = $entry['row'];
            $cellFont = $this->font($cell->style, $style);
            $padding = $this->padding($cell, $cell->style, $width, $cellFont);
            $borders = $this->collapsedWidths[$cell] ?? $this->borders($cell->style, $width, $cellFont);
            $decoration = $padding[1] + $padding[3] + $borders[1] + $borders[3];
            $declared = $this->length($cell->style['width'] ?? 'auto', $width, $cellFont, 'width');
            if (null !== $declared) {
                for ($i = $column; $i < $column + $span; ++$i) {
                    $specified[$i] = true;
                }
                if ($fixed && 0 === $rowIndex) {
                    $open = array_values(array_filter(range($column, $column + $span - 1), static fn (int $at): bool => !isset($given[$at])));
                    $left = $declared + $decoration - $gapX * ($span - 1) - array_sum(array_intersect_key($given, array_flip(range($column, $column + $span - 1))));
                    foreach ($open as $at) {
                        $given[$at] = max(0.0, $left / \count($open));
                    }
                }
            }
            $natural = $fixed ? 0.0 : max($this->intrinsicWidth($cell, $cellFont), $declared ?? 0.0) + $decoration;
            $entry['width'] = $natural;
            $entry['minimum'] = $fixed ? 0.0 : $this->minimumWidth($cell, $cellFont) + $decoration;
            $constraints[] = ['column' => $column, 'span' => $span, 'width' => $natural, 'minimum' => $entry['minimum']];
        }
        unset($entry);
        if (0 === $columns) {
            return [[], 0.0, 0.0];
        }
        if ($fixed) {
            $widths = TableGeometry::fixedColumns($columns, max(0.0, $width - $gapX * ($columns - 1)), $given);
        } else {
            foreach ($given as $column => $size) {
                $constraints[] = ['column' => $column, 'span' => 1, 'width' => $size, 'minimum' => $size];
            }
            $widths = TableGeometry::columns($constraints, $columns, $width, $gapX, 'auto' !== ($table->style['width'] ?? 'auto'), $specified);
        }
        $widths = $this->distribute($widths, false);
        $tableWidth = array_sum($widths) + $gapX * ($columns - 1);
        $rowMetrics = [];
        foreach ($rows as $rowIndex => $row) {
            $rowMetrics[] = ['row' => $rowIndex, 'span' => 1, 'height' => max(0.0, $this->length($row->style['height'] ?? '0', $height, $font, 'height', true) ?? 0.0)];
        }
        foreach ($cells as &$cell) {
            $cellWidth = array_sum(\array_slice($widths, $cell['column'], $cell['span'])) + $gapX * ($cell['span'] - 1);
            $box = $cell['box'];
            $box = $this->copiedBox($box, new Box($box->node, $box->display, array_replace($box->style, ['box-sizing' => 'border-box', 'margin' => '0']), $box->children, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries));
            $cell['fragment'] = $this->place($box, 0.0, 0.0, $cellWidth, $height, $style, $cellWidth);
            $rowMetrics[] = ['row' => $cell['row'], 'span' => $cell['rowSpan'], 'height' => $cell['fragment']->bounds->height];
        }
        unset($cell);
        $heights = $this->distribute(TableGeometry::rows($rowMetrics, \count($rows), $gapY), true);
        $rowY = [];
        $offset = 0.0;
        foreach ($heights as $index => $rowHeight) {
            $rowY[$index] = $offset;
            $offset += $rowHeight + $gapY;
        }
        $rowChildren = array_fill(0, \count($rows), []);
        foreach ($cells as $cell) {
            $fragment = $cell['fragment'];
            $cellX = array_sum(\array_slice($widths, 0, $cell['column'])) + $gapX * $cell['column'];
            $cellHeight = array_sum(\array_slice($heights, $cell['row'], $cell['rowSpan'])) + $gapY * ($cell['rowSpan'] - 1);
            $fragment = $this->translate($fragment, $x + $cellX, $y + $rowY[$cell['row']]);
            $extra = max(0.0, $cellHeight - $fragment->bounds->height);
            $align = $cell['box']->style['vertical-align'] ?? 'middle';
            $shift = 'bottom' === $align ? $extra : ('middle' === $align ? $extra / 2 : 0.0);
            $rowChildren[$cell['row']][] = new Fragment($fragment->node, new Rect($fragment->bounds->x, $fragment->bounds->y, $fragment->bounds->width, $cellHeight), $fragment->style, null, array_map(fn (Fragment $child): Fragment => $this->translate($child, 0.0, $shift), $fragment->children), $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
        }
        $rowFragments = [];
        foreach ($rows as $index => $row) {
            $rowFragments[] = new Fragment($row->node, new Rect($x, $y + $rowY[$index], $tableWidth, $heights[$index]), $this->tablePartStyle($row), null, $rowChildren[$index], null, null, [], null, $row->pseudoElement, $row->topLayer, null, null === $row->borderImage ? null : new BorderImageSource($row->borderImage), $row->anchor);
        }
        $children = [];
        $index = 0;
        foreach ($groups as $group) {
            $count = \count($group['rows']);
            $fragments = \array_slice($rowFragments, $index, $count);
            if (null === $group['box']) {
                array_push($children, ...$fragments);
            } elseif ($fragments) {
                $children[] = new Fragment($group['box']->node, new Rect($x, $y + $rowY[$index], $tableWidth, array_sum(\array_slice($heights, $index, $count)) + $gapY * ($count - 1)), $this->tablePartStyle($group['box']), null, $fragments, null, null, [], null, $group['box']->pseudoElement, $group['box']->topLayer, null, null === $group['box']->borderImage ? null : new BorderImageSource($group['box']->borderImage), $group['box']->anchor);
            }
            $index += $count;
        }

        $source = $this->boxOrigins[$table] ?? $table;
        if (isset($this->collapsedTables[$source])) {
            array_push($children, ...$this->collapsedSegments($this->collapsedTables[$source], $widths, $heights, $x, $y));
        }

        return [$children, max(0.0, $offset - $gapY), $tableWidth];
    }

    private function minimumWidth(Box $box, FontStyle $font): float
    {
        if ('ruby-pair' === $box->display) {
            return $this->intrinsicWidth($box, $font);
        }
        if (null !== $contained = $this->containedSize($box->style, false, $this->viewportWidth, $font)) {
            return $contained;
        }
        if (null !== $box->replacedContent) {
            return null !== $box->replacedContent->getIntrinsicSize()->aspectRatio ? min($box->replacedContent->getIntrinsicSize()->minimumWidth, $this->imageIntrinsicWidth($box, $font, $box->replacedContent)) : $box->replacedContent->getIntrinsicSize()->minimumWidth;
        }
        if (null !== $box->image) {
            return $this->imageIntrinsicWidth($box, $font, $box->image);
        }
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        if (null !== $text) {
            if (\in_array($box->style['white-space'] ?? 'normal', ['nowrap', 'pre'], true)) {
                return max(0.0, ...array_map(fn (string $line): float => $this->measureText($line, $font)->width, explode("\n", $text)));
            }
            $width = 0.0;
            foreach (LineBreaks::tokens($text, 'anywhere' === ($box->style['overflow-wrap'] ?? '') ? 'break-all' : ($box->style['word-break'] ?? 'normal')) as $word) {
                $parts = explode("\u{00AD}", $this->hyphenate($word, $box, $box->style));
                foreach ($parts as $index => $part) {
                    $width = max($width, $this->measureText(rtrim($part).($index < \count($parts) - 1 ? $this->hyphenCharacter($box->style) : ''), $font)->width);
                }
            }

            return $width;
        }
        $width = 0.0;
        $base = $this->intrinsicContentBase($box, $font);
        foreach ($box->children as $child) {
            if (str_contains($box->display, 'list-item') && 'marker' === $child->pseudoElement && 'inside' !== ($child->style['list-style-position'] ?? $box->style['list-style-position'] ?? 'outside')) {
                continue;
            }
            if ('none' !== $child->display) {
                $childFont = $this->font($child->style, ['font-family' => $font->family, 'font-size' => $font->size.'px', 'line-height' => $font->lineHeight.'px', 'font-weight' => (string) $font->weight, 'direction' => $font->direction, 'font-style' => $font->style, 'letter-spacing' => $font->letterSpacing.'px', 'word-spacing' => $font->wordSpacing.'px']);
                $width = max($width, $this->intrinsicChildContribution($child, $childFont, $base, true));
            }
        }

        return $width;
    }

    private function translate(Fragment $fragment, float $x, float $y): Fragment
    {
        $bounds = $fragment->bounds;
        $translated = new Fragment($fragment->node, new Rect($bounds->x + $x, $bounds->y + $y, $bounds->width, $bounds->height), $fragment->style, $fragment->text, array_map(fn (Fragment $child): Fragment => $this->translate($child, $x, $y), $fragment->children), $fragment->image, null === $fragment->transform ? null : Transform::moved($fragment->transform, $x, $y), $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll?->moved($x, $y), $fragment->borderImage, $fragment->anchor, $fragment->content, $fragment->surfaceBackground, $fragment->outline);
        if (isset($this->positionedBoxes[$fragment])) {
            $this->positionedBoxes[$translated] = $this->positionedBoxes[$fragment];
        }
        if (isset($this->anchorSources[$fragment])) {
            $this->anchorSources[$translated] = $this->anchorSources[$fragment];
        }

        return $translated;
    }

    private function font(array $style, array $inherited): FontStyle
    {
        $family = $style['font-family'] ?? $inherited['font-family'] ?? 'sans-serif';
        $weight = (int) ($style['font-weight'] ?? $inherited['font-weight'] ?? 400);
        $direction = $style['direction'] ?? $inherited['direction'] ?? 'ltr';
        $fontStyle = strtolower(trim($style['font-style'] ?? $inherited['font-style'] ?? 'normal'));
        $fontStyle = \in_array($fontStyle, ['normal', 'italic', 'oblique'], true) ? $fontStyle : 'normal';
        $parentSize = (float) ($inherited['font-size'] ?? 16);
        $parentFont = new FontStyle($inherited['font-family'] ?? $family, $parentSize, (float) ($inherited['line-height'] ?? $parentSize * 1.2), (int) ($inherited['font-weight'] ?? $weight), $inherited['direction'] ?? $direction, $inherited['font-style'] ?? $fontStyle);
        $size = $this->length($style['font-size'] ?? $parentSize.'px', $parentSize, $parentFont, 'font-size') ?? $parentSize;
        $metricsFont = new FontStyle($family, max(0.0, $size), max(0.0, $size * 1.2), $weight, $direction, $fontStyle);
        $line = $style['line-height'] ?? 'normal';
        $lineHeight = 'normal' === $line ? $size * 1.2 : (is_numeric($line) && !isset($inherited['line-height']) ? (float) $line * $size : ($this->length($line, $size, $metricsFont, 'line-height', true) ?? $size * 1.2));

        $letterSpacing = $this->length($style['letter-spacing'] ?? $inherited['letter-spacing'] ?? 'normal', 0.0, $metricsFont, 'letter-spacing') ?? 0.0;
        $wordSpacing = $this->length($style['word-spacing'] ?? $inherited['word-spacing'] ?? 'normal', 0.0, $metricsFont, 'word-spacing') ?? 0.0;
        $font = new FontStyle($family, max(0.0, $size), max(0.0, $lineHeight), $weight, $direction, $fontStyle, $letterSpacing, $wordSpacing);
        $lineHeight = $this->layoutMetrics?->constrainLength($font->lineHeight, 'line-height', true, $font) ?? $font->lineHeight;

        return $lineHeight === $font->lineHeight ? $font : new FontStyle($font->family, $font->size, max(0.0, $lineHeight), $font->weight, $font->direction, $font->style, $font->letterSpacing, $font->wordSpacing);
    }

    private function borders(array $style, float $base, FontStyle $font): array
    {
        $values = \Symfony\Component\HtmlRenderer\Css\ValueTokenizer::split($style['border-width'] ?? '0');
        $values = match (\count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => [...\array_slice($values, 0, 4), ...array_fill(0, max(0, 4 - \count($values)), '0')],
        };
        foreach (['top', 'right', 'bottom', 'left'] as $index => $edge) {
            $value = $style['border-'.$edge.'-width'] ?? $values[$index];
            $value = match ($value) {
                'thin' => '1px',
                'medium' => '3px',
                'thick' => '5px',
                default => $value,
            };
            $borderStyle = $style['border-'.$edge.'-style'] ?? $style['border-style'] ?? 'solid';
            $purpose = ('1' === ($style['-renderer-inline-decoration'] ?? null) ? 'inline-border-' : 'border-').$edge.'-width';
            $values[$index] = \in_array($borderStyle, ['none', 'hidden'], true) ? 0.0 : max(0.0, $this->length($value, $base, $font, $purpose, \in_array($edge, ['top', 'bottom'], true)) ?? 0.0);
        }

        return $values;
    }

    private function edges(array $style, string $property, float $base, FontStyle $font): array
    {
        $values = preg_split('/\s+/', trim($style[$property] ?? '0')) ?: ['0'];
        $values = match (\count($values)) {
            1 => [$values[0], $values[0], $values[0], $values[0]],
            2 => [$values[0], $values[1], $values[0], $values[1]],
            3 => [$values[0], $values[1], $values[2], $values[1]],
            default => \array_slice($values, 0, 4),
        };
        foreach (['top', 'right', 'bottom', 'left'] as $i => $edge) {
            $values[$i] = $this->length($style[$property.'-'.$edge] ?? $values[$i], $base, $font, $property.'-'.$edge, \in_array($edge, ['top', 'bottom'], true)) ?? 0.0;
            if ('padding' === $property) {
                $values[$i] = max(0.0, $values[$i]);
            }
        }

        return $values;
    }

    private function inlineOuterWidth(Box $box, float $width, FontStyle $font): float
    {
        $margin = $this->edges($box->style, 'margin', $width, $font);
        $padding = $this->padding($box, $box->style, $width, $font);
        $border = $this->borders($box->style, $width, $font);
        $chrome = $padding[1] + $padding[3] + $border[1] + $border[3];
        $available = max(0.0, $width - $margin[1] - $margin[3] - $chrome);
        $intrinsic = $this->intrinsicKeywordWidth($box, $font, $box->style['width'] ?? 'auto', $available, $width, $chrome);
        $specified = $this->length($box->style['width'] ?? 'auto', $width, $font, 'width');
        $borderWidth = null !== $intrinsic ? $intrinsic + $chrome : (null !== $specified ? $specified + ('border-box' === ($box->style['box-sizing'] ?? '') ? 0.0 : $chrome) : $this->intrinsicWidth($box, $font) + $chrome);

        return $this->constrain($borderWidth, $box->style, 'width', $width, $font, $chrome) + $margin[1] + $margin[3];
    }

    private function intrinsicKeywordWidth(Box $box, FontStyle $font, string $value, float $available, float $base, float $chrome = 0.0): ?float
    {
        if (false === stripos($value, 'content')) {
            return null;
        }
        $value = strtolower(trim($value));
        if (str_starts_with($value, '-')) {
            $value = (string) preg_replace('/^-(?:webkit|moz)-/', '', $value);
        }
        if ('min-content' === $value) {
            return $this->minimumWidth($box, $font);
        }
        if ('max-content' === $value) {
            return $this->intrinsicWidth($box, $font);
        }
        if (preg_match('/^fit-content\((.+)\)$/s', $value, $match)) {
            $available = $this->length($match[1], $base, $font, 'width');
            if (null === $available || $available < 0) {
                return null;
            }
            if ('border-box' === ($box->style['box-sizing'] ?? '')) {
                $available = max(0.0, $available - $chrome);
            }
        } elseif ('fit-content' !== $value) {
            return null;
        }

        return min($this->intrinsicWidth($box, $font), max($this->minimumWidth($box, $font), $available));
    }

    private function imageRatio(Box $box): ?float
    {
        $value = strtolower(trim($box->style['aspect-ratio'] ?? 'auto'));
        if (preg_match('/(?:^|\s)auto(?:$|\s)/', $value)) {
            return null;
        }
        $ratio = \Symfony\Component\HtmlRenderer\Css\MediaQuery::ratio($value);

        return null !== $ratio && $ratio > 0 && is_finite($ratio) ? $ratio : null;
    }

    /** Intrinsic contributions are content-box sizes; percentages need a definite containing axis. */
    private function imageIntrinsicWidth(Box $box, FontStyle $font, ImageResource|ReplacedContentInterface $content, ?float $baseWidth = null, ?float $baseHeight = null): float
    {
        $style = $box->style;
        $intrinsic = null;
        if ($content instanceof ImageResource) {
            $naturalWidth = $content->width;
            $naturalRatio = $content->width / $content->height;
        } else {
            $intrinsic = $content->getIntrinsicSize();
            $naturalWidth = $intrinsic->preferredWidth;
            $naturalRatio = $intrinsic->aspectRatio;
        }
        $padding = $this->padding($box, $style, $baseWidth ?? $this->viewportWidth, $font);
        $border = $this->borders($style, $baseWidth ?? $this->viewportWidth, $font);
        $horizontal = $padding[1] + $padding[3] + $border[1] + $border[3];
        $vertical = $padding[0] + $padding[2] + $border[0] + $border[2];
        $borderBox = 'border-box' === ($style['box-sizing'] ?? '');
        $widthValue = $style['width'] ?? 'auto';
        $width = null === $baseWidth && str_contains($widthValue, '%') ? null : $this->length($widthValue, $baseWidth ?? $this->viewportWidth, $font, 'width');
        if (null !== $width) {
            return max(0.0, $width - ($borderBox ? $horizontal : 0.0));
        }
        $heightValue = $style['height'] ?? 'auto';
        $height = null === $baseHeight && str_contains($heightValue, '%') ? null : $this->length($heightValue, $baseHeight ?? $this->viewportHeight, $font, 'height', true);
        $ratio = $this->imageRatio($box);
        $heightStyle = $style;
        if (null === $baseHeight) {
            foreach (['min-height', 'max-height'] as $property) {
                if (str_contains($heightStyle[$property] ?? '', '%')) {
                    unset($heightStyle[$property]);
                }
            }
        }
        if (null === $height) {
            $naturalWidth = null !== $intrinsic ? $naturalWidth : ($this->length($naturalWidth.'px', $baseWidth ?? $this->viewportWidth, $font, 'width') ?? 0.0);
            if (\in_array($heightStyle['min-height'] ?? 'auto', ['auto', '0', '0px'], true) && \in_array($heightStyle['max-height'] ?? 'none', ['none', 'auto'], true)) {
                return $naturalWidth;
            }
            if (null !== $ratio && $borderBox) {
                $borderHeight = ($naturalWidth + $horizontal) / $ratio;
            } else {
                $naturalHeight = null !== $intrinsic && null === $ratio ? $intrinsic->height : $naturalWidth / ($ratio ?? $naturalRatio);
                $naturalHeight = $this->layoutMetrics?->constrainLength($naturalHeight, 'height', true) ?? $naturalHeight;
                $borderHeight = $naturalHeight + $vertical;
            }
            $bounded = $this->constrain($borderHeight, $heightStyle, 'height', $baseHeight ?? $this->viewportHeight, $font, $vertical);
            if ($bounded === $borderHeight) {
                return $naturalWidth;
            }
            $borderHeight = $bounded;
        } else {
            $borderHeight = $this->constrain($height + ($borderBox ? 0.0 : $vertical), $heightStyle, 'height', $baseHeight ?? $this->viewportHeight, $font, $vertical);
        }
        $width = null !== $ratio && $borderBox ? max(0.0, $borderHeight * $ratio - $horizontal) : max(0.0, $borderHeight - $vertical) * ($ratio ?? $naturalRatio);

        return $this->layoutMetrics?->constrainLength($width, 'width', false) ?? $width;
    }

    private function intrinsicWidth(Box $box, FontStyle $font): float
    {
        if (null !== $contained = $this->containedSize($box->style, false, $this->viewportWidth, $font)) {
            return $contained;
        }
        if (null !== $box->replacedContent) {
            return null !== $box->replacedContent->getIntrinsicSize()->aspectRatio ? $this->imageIntrinsicWidth($box, $font, $box->replacedContent) : $box->replacedContent->getIntrinsicSize()->preferredWidth;
        }
        if (null !== $box->image) {
            return $this->imageIntrinsicWidth($box, $font, $box->image);
        }
        $text = $box->text ?? ($box->node instanceof \Dom\Text ? $box->node->textContent : null);
        if (null !== $text) {
            if ('1' === ($box->style['-renderer-collapse-leading-space'] ?? null)) {
                $text = ltrim($text, 'pre-line' === ($box->style['white-space'] ?? 'normal') ? " \t\r\f" : " \t\r\n\f");
            }
            $paragraphs = $this->paragraphs(str_replace("\u{00AD}", '', $text), $box->style['white-space'] ?? 'normal');

            return max(0.0, ...array_map(fn (string $paragraph): float => $this->measureText($paragraph, $font)->width, $paragraphs));
        }
        $width = $inline = 0.0;
        $base = $this->intrinsicContentBase($box, $font);
        $blockFlow = \in_array($box->display, ['block', 'flow-root', 'list-item', 'inline', 'inline-block'], true);
        foreach ($box->children as $child) {
            if (str_contains($box->display, 'list-item') && 'marker' === $child->pseudoElement && 'inside' !== ($child->style['list-style-position'] ?? $box->style['list-style-position'] ?? 'outside')) {
                continue;
            }
            if ('none' === $child->display || null !== $child->topLayer || \in_array($child->style['position'] ?? 'static', ['absolute', 'fixed'], true)) {
                continue;
            }
            $childFont = $this->font($child->style, ['font-family' => $font->family, 'font-size' => $font->size.'px', 'line-height' => $font->lineHeight.'px', 'font-weight' => (string) $font->weight, 'direction' => $font->direction, 'font-style' => $font->style, 'letter-spacing' => $font->letterSpacing.'px', 'word-spacing' => $font->wordSpacing.'px']);
            $outer = $this->intrinsicChildContribution($child, $childFont, $base, false);
            if ('ruby-pair' === $box->display) {
                $width = max($width, $outer);
                continue;
            }
            if (!$blockFlow || \in_array($child->display, ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'inline-table', 'ruby', 'ruby-pair'], true) || \in_array($child->style['float'] ?? 'none', ['left', 'right'], true)) {
                $inline += $outer;
            } else {
                $width = max($width, $inline, $outer);
                $inline = 0.0;
            }
        }

        return max($width, $inline);
    }

    private function intrinsicChildContribution(Box $child, FontStyle $font, ?float $base, bool $minimum): float
    {
        $style = $child->style;
        $reference = $base ?? 0.0;
        $padding = $this->padding($child, $style, $reference, $font);
        $border = $this->borders($style, $reference, $font);
        $margin = $this->edges($style, 'margin', $reference, $font);
        $chrome = $padding[1] + $padding[3] + $border[1] + $border[3];
        $value = $style['width'] ?? 'auto';
        $specified = null === $base && str_contains($value, '%') ? null : $this->length($value, $reference, $font, 'width');
        $natural = $minimum ? $this->minimumWidth($child, $font) : $this->intrinsicWidth($child, $font);
        $outer = null === $specified ? $natural + $chrome : $specified + ('border-box' === ($style['box-sizing'] ?? '') ? 0.0 : $chrome);
        if (null === $base) {
            foreach (['min-width', 'max-width'] as $property) {
                if (str_contains($style[$property] ?? '', '%')) {
                    unset($style[$property]);
                }
            }
        }

        return $this->constrain($outer, $style, 'width', $reference, $font, $chrome) + $margin[1] + $margin[3];
    }

    private function intrinsicContentBase(Box $box, FontStyle $font): ?float
    {
        $style = $box->style;
        foreach (['width', 'min-width', 'max-width'] as $property) {
            if (str_contains($style[$property] ?? '', '%')) {
                return null;
            }
        }
        if (null === $width = $this->length($style['width'] ?? 'auto', $this->viewportWidth, $font, 'width')) {
            return null;
        }
        if ('border-box' === ($style['box-sizing'] ?? '')) {
            foreach (['padding', 'padding-left', 'padding-right'] as $property) {
                if (str_contains($style[$property] ?? '', '%')) {
                    return null;
                }
            }
        }
        $padding = $this->padding($box, $style, $this->viewportWidth, $font);
        $border = $this->borders($style, $this->viewportWidth, $font);
        $chrome = $padding[1] + $padding[3] + $border[1] + $border[3];
        $outer = $width + ('border-box' === ($style['box-sizing'] ?? '') ? 0.0 : $chrome);

        return max(0.0, $this->constrain($outer, $style, 'width', $this->viewportWidth, $font, $chrome) - $chrome);
    }

    private function trimBoundary(Box $box, bool $end): Box
    {
        $copy = InlineFormatting::trimBoundary($box, $end);
        if (null !== $this->layoutMetrics) {
            $remember = function (Box $source, Box $copy) use (&$remember): void {
                $this->copiedBox($source, $copy);
                foreach ($copy->children as $index => $child) {
                    $remember($source->children[$index], $child);
                }
            };
            $remember($box, $copy);
        }

        return $copy;
    }

    private function copiedBox(Box $source, Box $copy): Box
    {
        $this->boxOrigins[$copy] = $this->boxOrigins[$source] ?? $source;
        if (isset($this->markerGutters[$source])) {
            $this->markerGutters[$copy] = $this->markerGutters[$source];
        }

        return $copy;
    }

    private function scanScrollbars(Box $box, array $inherited): void
    {
        if ('none' === $box->display) {
            return;
        }
        $style = array_replace(array_intersect_key($inherited, ['direction' => true]), $box->style);
        $this->scrollbarKey($box, $this->tableOverflow($box, $style));
        foreach ($box->children as $child) {
            $this->scanScrollbars($child, $style);
        }
    }

    private function tableOverflow(Box $box, array $style): array
    {
        if (\in_array($box->display, ['table', 'inline-table'], true)) {
            foreach (['x', 'y'] as $axis) {
                $property = 'overflow-'.$axis;
                if (\in_array($style[$property] ?? $style['overflow'] ?? 'visible', ['auto', 'scroll'], true)) {
                    $style[$property] = 'visible';
                }
            }
        }

        return $style;
    }

    private function scrollbarKey(Box $box, array $style): ?int
    {
        if (null === $this->layoutMetrics || \in_array($box->display, ['inline', 'ruby', 'ruby-base', 'ruby-text', 'ruby-pair', 'none'], true) || 'none' === ($style['scrollbar-width'] ?? 'auto')) {
            return null;
        }
        $x = $style['overflow-x'] ?? $style['overflow'] ?? 'visible';
        $y = $style['overflow-y'] ?? $style['overflow'] ?? 'visible';
        $stable = str_contains($style['scrollbar-gutter'] ?? 'auto', 'stable') && \in_array($y, ['auto', 'scroll', 'hidden'], true);
        if (!\in_array($x, ['auto', 'scroll'], true) && !\in_array($y, ['auto', 'scroll'], true) && !$stable) {
            return null;
        }
        $owner = $this->boxOrigins[$box] ?? $box;
        $role = $box->pseudoElement ?? '';
        $keys = $this->scrollKeys[$owner] ?? [];
        if (isset($keys[$role])) {
            return $keys[$role];
        }
        $thin = 'thin' === ($style['scrollbar-width'] ?? 'auto');
        $vertical = $this->layoutMetrics->scrollbarThickness(true, $thin);
        $horizontal = $this->layoutMetrics->scrollbarThickness(false, $thin);
        if (!is_finite($vertical) || !is_finite($horizontal) || $vertical < 0 || $horizontal < 0) {
            throw new \InvalidArgumentException('Scrollbar thickness must be finite and non-negative.');
        }
        if (0.0 === $vertical && 0.0 === $horizontal) {
            return null;
        }
        $key = \count($this->scrollbars);
        $keys[$role] = $key;
        $this->scrollKeys[$owner] = $keys;
        $this->scrollbars[$key] = ['overflow-x' => $x, 'overflow-y' => $y, 'stable' => $stable, 'both' => $stable && str_contains($style['scrollbar-gutter'], 'both-edges'), 'rtl' => 'rtl' === ($style['direction'] ?? 'ltr'), 'thin' => $thin, 'horizontal' => $horizontal, 'vertical' => $vertical, 'x' => 'scroll' === $x && $horizontal > 0, 'y' => 'scroll' === $y && $vertical > 0, 'lock-x' => false, 'lock-y' => false];

        return $key;
    }

    private function scrollbarInsets(?int $key): array
    {
        if (null === $key) {
            return [0.0, 0.0, 0.0, 0.0];
        }
        $policy = $this->scrollbars[$key];
        $insets = [0.0, 0.0, $policy['x'] ? $policy['horizontal'] : 0.0, 0.0];
        if ($policy['y'] || $policy['stable']) {
            $insets[$policy['rtl'] ? 3 : 1] = $policy['vertical'];
            if ($policy['both']) {
                $insets[$policy['rtl'] ? 1 : 3] = $policy['vertical'];
            }
        }

        return $insets;
    }

    private function scrollPort(Rect $padding, array $reserved): Rect
    {
        return new Rect($padding->x + $reserved[3], $padding->y + $reserved[0], max(0.0, $padding->width - $reserved[1] - $reserved[3]), max(0.0, $padding->height - $reserved[0] - $reserved[2]));
    }

    private function prepareMarkerGutters(Box $root): void
    {
        $groups = new \SplObjectStorage();
        $walk = function (Box $box, array $inherited, ?Box $parent) use (&$walk, $groups): void {
            if ('none' === $box->display) {
                return;
            }
            $style = array_replace(array_intersect_key($inherited, array_flip(['font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing', 'line-height', 'direction', 'list-style-position'])), $box->style);
            if (str_contains($box->display, 'list-item') && 'inside' !== ($style['list-style-position'] ?? 'outside')) {
                $width = 0.0;
                foreach ($box->children as $marker) {
                    if ('marker' === $marker->pseudoElement && 'none' !== $marker->display) {
                        $width = max($width, $this->intrinsicWidth($marker, $this->font($marker->style, $style)));
                    }
                }
                $group = $box->node->parentNode ?? $parent ?? $box;
                if ($width > 0) {
                    $entry = $groups[$group] ?? ['width' => 0.0, 'items' => []];
                    $entry['width'] = max($entry['width'], $width);
                    $entry['items'][] = $box;
                    $groups[$group] = $entry;
                }
            }
            foreach ($box->children as $child) {
                $walk($child, $style, $box);
            }
        };
        $walk($root, [], null);
        foreach ($groups as $group) {
            $entry = $groups[$group];
            $gutter = $this->layoutMetrics->outsideMarkerGutter($entry['width']);
            if (!is_finite($gutter) || $gutter < 0) {
                throw new \InvalidArgumentException('The outside marker gutter must be finite and non-negative.');
            }
            foreach ($entry['items'] as $item) {
                $this->markerGutters[$item] = $gutter;
                if (null !== $item->node) {
                    $this->markerNodes[$item->node] = $gutter;
                }
            }
        }
    }

    private function padding(Box $box, array $style, float $base, FontStyle $font): array
    {
        $padding = $this->edges($style, 'padding', $base, $font);
        if (str_contains($box->display, 'list-item')) {
            $gutter = $this->markerGutters[$box] ?? (null !== $box->node ? ($this->markerNodes[$box->node] ?? 0.0) : 0.0);
            $padding['rtl' === ($style['direction'] ?? 'ltr') ? 1 : 3] += $gutter;
        }

        return $padding;
    }

    private function fontKey(FontStyle $font): ?array
    {
        if (\strlen($font->family) > 256 || \strlen($font->style) > 256 || !\in_array($font->style, ['normal', 'italic', 'oblique'], true) || !\in_array($font->direction, ['ltr', 'rtl', 'auto'], true)) {
            return null;
        }

        return [$font->family, pack('dddd', $font->size, $font->lineHeight, $font->letterSpacing, $font->wordSpacing), $font->weight, $font->direction, $font->style];
    }

    private function measureText(string $text, FontStyle $font): TextMetrics
    {
        if (\strlen($text) > 256 || null === $fontKey = $this->fontKey($font)) {
            return $this->textMeasurer->measure($text, $font);
        }
        // Binary floats keep distinct metrics distinct regardless of serialize_precision.
        $key = serialize([$text, $fontKey]);
        if (isset($this->textMeasurements[$key])) {
            return $this->textMeasurements[$key];
        }
        $metrics = $this->textMeasurer->measure($text, $font);
        if (\count($this->textMeasurements) < 1024) {
            $this->textMeasurements[$key] = $metrics;
        }

        return $metrics;
    }

    private function length(string|int|float $value, float $base, FontStyle $font, string $property = 'length', bool $vertical = false, bool $constrain = true): ?float
    {
        $key = null;
        if (\strlen((string) $value) <= 128 && null !== $fontKey = $this->fontKey($font)) {
            $key = serialize([(string) $value, pack('d', $base), $fontKey, $property, $vertical, $constrain]);
            if (\array_key_exists($key, $this->lengthCache)) {
                return $this->lengthCache[$key];
            }
            if (\count($this->lengthCache) >= 4096) {
                $key = null;
            }
        }
        $math = Length::isMath((string) $value);
        $resolved = Length::evaluate((string) $value, function (string $primitive) use ($base, $font, $vertical, $math, $property): ?float {
            if (!preg_match('/^(-?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(px|pt|pc|in|cm|mm|q|%|em|rem|ch|lh|vw|vh|vmin|vmax)?$/i', trim($primitive), $matches)) {
                return null;
            }
            $number = (float) $matches[1];

            $unit = strtolower($matches[2] ?? '');
            if ($math && '' === $unit) {
                return $number;
            }
            $primitiveValue = Length::absolute($number, $unit) ?? $number * match ($unit) {
                '%' => $base / 100,
                'em' => $font->size,
                'rem' => 16.0,
                'ch' => $this->measureText('0', new FontStyle($font->family, $font->size, $font->lineHeight, $font->weight, $font->direction, $font->style))->width,
                'lh' => $font->lineHeight,
                'vw' => $this->viewportWidth / 100,
                'vh' => $this->viewportHeight / 100,
                'vmin' => min($this->viewportWidth, $this->viewportHeight) / 100,
                'vmax' => max($this->viewportWidth, $this->viewportHeight) / 100,
                default => 1.0,
            };

            return \in_array($property, ['font-size', 'line-height'], true) ? $primitiveValue : ($this->layoutMetrics?->resolveUnit($number, $unit, $vertical, $primitiveValue) ?? $primitiveValue);
        });

        if (null === $resolved || !is_finite($resolved)) {
            if (null !== $key) {
                $this->lengthCache[$key] = null;
            }

            return null;
        }
        if ($constrain && 'font-size' !== $property) {
            $resolved = $this->layoutMetrics?->constrainLength($resolved, str_contains((string) $value, '%') && \in_array($property, ['top', 'right', 'bottom', 'left'], true) ? 'percentage-inset' : $property, $vertical) ?? $resolved;
        }

        $resolved = is_finite($resolved) ? $resolved : null;
        if (null !== $key) {
            $this->lengthCache[$key] = $resolved;
        }

        return $resolved;
    }

    private function paragraphs(string $text, string $whiteSpace): array
    {
        if (\in_array($whiteSpace, ['pre', 'pre-wrap', 'pre-line', 'break-spaces'], true)) {
            $paragraphs = preg_split('/\r\n|\r|\n/', $text) ?: [''];
            if ('pre-line' === $whiteSpace) {
                $last = \count($paragraphs) - 1;
                foreach ($paragraphs as $index => &$paragraph) {
                    $paragraph = (string) preg_replace('/[\t\f ]+/', ' ', $paragraph);
                    if ($index > 0) {
                        $paragraph = ltrim($paragraph, ' ');
                    }
                    if ($index < $last) {
                        $paragraph = rtrim($paragraph, ' ');
                    }
                }
                unset($paragraph);
            }

            return $paragraphs;
        }

        return [(string) preg_replace('/[\t\r\n\f ]+/', ' ', $text)];
    }

    private function hyphenate(string $text, Box $box, array $style): string
    {
        if ('none' === ($style['hyphens'] ?? 'manual')) {
            return str_replace("\u{00AD}", '', $text);
        }
        $hyphenator = null;
        if (null !== $this->hyphenator && 'auto' === ($style['hyphens'] ?? 'manual')) {
            for ($node = $box->node; null !== $node; $node = $node->parentNode) {
                if ($node instanceof \Dom\Element && $node->hasAttribute('lang')) {
                    $language = strtolower(trim($node->getAttribute('lang')));
                    if ('' !== $language && \strlen($language) <= 256) {
                        if (!isset($this->hyphenators[$language]) && \count($this->hyphenators) >= 64) {
                            $this->hyphenators = [];
                        }
                        $hyphenator = $this->hyphenators[$language] ??= new LanguageHyphenator($this->hyphenator, $language);
                    }
                    break;
                }
            }
        }

        return Hyphenation::prepare($text, null === $hyphenator ? null : $hyphenator->hyphenate(...), true, Hyphenation::limits($style['hyphenate-limit-chars'] ?? 'auto'));
    }

    private function hyphenCharacter(array $style): string
    {
        $value = $style['hyphenate-character'] ?? 'auto';

        return Hyphenation::character($value) ?? '-';
    }

    private function lines(string $text, float $width, FontStyle $font, string $whiteSpace, ?\Closure $available = null, string $wordBreak = 'normal', string $hyphen = '-', ?array &$sourceLines = null, bool $anywhere = false): array
    {
        $paragraphs = $this->paragraphs($text, $whiteSpace);
        if (\in_array($whiteSpace, ['pre', 'nowrap'], true)) {
            if (null !== $sourceLines) {
                $sourceLines = $paragraphs;
            }

            return array_map(static fn (string $line): string => str_replace("\u{00AD}", '', $line), $paragraphs);
        }
        $lines = [];
        foreach ($paragraphs as $paragraph) {
            $line = $sourceLine = '';
            $pending = array_reverse(LineBreaks::tokens($paragraph, $wordBreak) ?: ['']);
            while ($pending) {
                $word = array_pop($pending);
                $wordWidth = $this->measureText(rtrim(str_replace("\u{00AD}", '', $word)), $font)->width;
                while ('' === $line && null !== $available && $wordWidth <= $width && $wordWidth > $available(\count($lines)) && \count($lines) < 10000) {
                    $lines[] = '';
                    if (null !== $sourceLines) {
                        $sourceLines[] = '';
                    }
                }
                while (true) {
                    $clean = str_replace("\u{00AD}", '', $word);
                    $room = null === $available ? $width : $available(\count($lines));
                    if ($this->measureText(rtrim($line.$clean), $font)->width <= $room) {
                        $line .= $clean;
                        if (null !== $sourceLines) {
                            $sourceLine .= $word;
                        }
                        break;
                    }
                    $parts = explode("\u{00AD}", $word);
                    $prefix = '';
                    $split = null;
                    foreach ($parts as $index => $part) {
                        $prefix .= $part;
                        if ($index >= \count($parts) - 1 || '' === $prefix) {
                            continue;
                        }
                        // Greedy line breaking stops at the first opportunity that exceeds the current line.
                        if ($this->measureText($line.$prefix.$hyphen, $font)->width > $room) {
                            break;
                        }
                        $split = $index;
                    }
                    if (null !== $split) {
                        $lines[] = $line.implode('', \array_slice($parts, 0, $split + 1)).$hyphen;
                        if (null !== $sourceLines) {
                            $sourceLines[] = $sourceLine.implode("\u{00AD}", \array_slice($parts, 0, $split + 1))."\u{00AD}";
                        }
                        $line = $sourceLine = '';
                        $word = implode("\u{00AD}", \array_slice($parts, $split + 1));
                    } elseif ('' !== $line) {
                        $lines[] = rtrim($line);
                        if (null !== $sourceLines) {
                            $sourceLines[] = rtrim($sourceLine);
                        }
                        $line = $sourceLine = '';
                        $word = ltrim($word);
                    } elseif ($anywhere && preg_match_all('/\\X/u', $word, $graphemes) > 1) {
                        $word = array_shift($graphemes[0]);
                        array_push($pending, ...array_reverse($graphemes[0]));
                    } else {
                        $line = $clean;
                        if (null !== $sourceLines) {
                            $sourceLine = $word;
                        }
                        break;
                    }
                }
            }
            $lines[] = $line;
            if (null !== $sourceLines) {
                $sourceLines[] = $sourceLine;
            }
        }

        return $lines;
    }

    private function snapScroll(Fragment $owner, array $children, ScrollGeometry $scroll): ScrollGeometry
    {
        if ('none' === ($owner->style['scroll-snap-type'] ?? 'none')) {
            return $scroll;
        }
        $offsets = [$scroll->offsetX, $scroll->offsetY];
        $snapped = [];
        foreach ([false, true] as $vertical) {
            $maximum = $vertical ? $scroll->maxY : $scroll->maxX;
            $mode = ScrollSnapGeometry::mode($owner->style['scroll-snap-type'] ?? 'none', $vertical);
            if (!$mode || $maximum <= 0) {
                continue;
            }
            $extent = $vertical ? $scroll->port->height : $scroll->port->width;
            $origin = $vertical ? $scroll->port->y : $scroll->port->x;
            $font = $this->font($owner->style, []);
            $padding = $this->edges($owner->style, 'scroll-padding', $extent, $font);
            [$beforeIndex, $afterIndex] = $vertical ? [0, 2] : [3, 1];
            $points = [];
            $collect = function (array $fragments) use (&$collect, &$points, $vertical, $maximum, $extent, $origin, $padding, $beforeIndex, $afterIndex): void {
                foreach ($fragments as $fragment) {
                    if (null !== $fragment->pseudoElement) {
                        continue;
                    }
                    $alignment = ScrollSnapGeometry::alignment($fragment->style['scroll-snap-align'] ?? 'none', $vertical);
                    if ($fragment->node instanceof \Dom\Element && null !== $alignment) {
                        $margin = $this->edges($fragment->style, 'scroll-margin', $extent, $this->font($fragment->style, []));
                        $start = ($vertical ? $fragment->bounds->y : $fragment->bounds->x) - $origin;
                        $size = $vertical ? $fragment->bounds->height : $fragment->bounds->width;
                        $position = ScrollSnapGeometry::position($alignment, $start, $size, $margin[$beforeIndex], $margin[$afterIndex], $padding[$beforeIndex], $padding[$afterIndex], $extent, $maximum);
                        $position = $this->layoutMetrics?->constrainLength($position, 'scroll-snap-position', $vertical) ?? $position;
                        $points[] = [max(0.0, min($maximum, $position)), 'always' === ($fragment->style['scroll-snap-stop'] ?? 'normal'), $fragment->node];
                    }
                    if (null === $fragment->scroll) {
                        $collect($fragment->children);
                    }
                }
            };
            $collect($children);
            $proximity = 'proximity' === $mode ? ($this->layoutMetrics?->constrainLength($extent / 3, 'scroll-snap-proximity', $vertical) ?? $extent / 3) : null;
            $previous = $owner->style[$vertical ? '--html-scroll-snap-y' : '--html-scroll-snap-x'] ?? null;
            $offset = ScrollSnapGeometry::select(array_map(static fn (array $point): array => [$point[0], $point[1]], $points), $offsets[$vertical ? 1 : 0], null === $previous ? null : (float) $previous, $proximity);
            $offsets[$vertical ? 1 : 0] = $offset;
            foreach ($points as [$position, , $target]) {
                if ($position === $offset) {
                    $snapped[$vertical ? 'y' : 'x'][] = $target;
                }
            }
        }

        if ($offsets === [$scroll->offsetX, $scroll->offsetY] && !$snapped) {
            return $scroll;
        }

        return new ScrollGeometry($scroll->port, $scroll->content, $offsets[0], $offsets[1], $scroll->horizontal, $scroll->vertical, $scroll->reserved, $scroll->horizontalBar, $scroll->verticalBar, $scroll->cycleResolved, $snapped);
    }
}
