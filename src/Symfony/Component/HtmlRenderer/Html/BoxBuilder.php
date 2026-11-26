<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Css\ContentResolver;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Form\SelectOptions;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\InlineFormatting;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

/** @internal */
final class BoxBuilder
{
    private ContentResolver $content;
    private int $firstLineSequence = 0;
    private \SplObjectStorage $sourceTexts;
    private array $highlights = [];
    private \SplObjectStorage $scrollMarkers;

    private const INHERITED_INLINE = ['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'white-space', 'letter-spacing', 'word-spacing', 'text-align', 'text-align-last', 'text-transform', 'text-justify', 'word-break', 'overflow-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', 'text-decoration', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness', '-webkit-text-fill-color', 'visibility', 'direction'];

    private ?\SplObjectStorage $projectedNodes = null;
    private int $projectedItems = 0;
    private int $remainingInlineFragments = 100000;
    private int $remainingPositionAlternatives = 100000;
    private ?array $namedAnchors = null;

    /**
     * @param \SplObjectStorage<\Dom\Element, array<string, string>>   $styles
     * @param \SplObjectStorage<\Dom\Element, array<array-key, mixed>> $states
     * @param \SplObjectStorage<\Dom\Node, list<mixed>>|null           $anonymousGroups Native children and presentation descriptors, validated while building
     * @param \SplObjectStorage<\Dom\Node, array<string, array>>|null  $usedStyles      Presentation styles by visual role, validated when read
     */
    public function __construct(private readonly \SplObjectStorage $styles, private readonly StyleResolver $resolver, private readonly ResourceCollection $resources, private readonly \SplObjectStorage $states, private readonly ?\Closure $replacedContentResolver = null, private readonly ?\SplObjectStorage $anonymousGroups = null, private readonly ?\SplObjectStorage $usedStyles = null)
    {
        $this->content = new ContentResolver($resolver->getCounterStyles());
        $this->sourceTexts = new \SplObjectStorage();
        $this->scrollMarkers = new \SplObjectStorage();
        foreach ($states as $element) {
            if ($element === $element->ownerDocument->documentElement) {
                $this->highlights = ($states[$element] ?? [])['highlights'] ?? [];
            }
        }
    }

    public function build(\Dom\Node $node, array $inherited = [], int $depth = 0): Box
    {
        if (0 === $depth) {
            $this->remainingInlineFragments = 100000;
            $this->remainingPositionAlternatives = 100000;
            $this->namedAnchors = null;
            $this->scrollMarkers = new \SplObjectStorage();
        }
        if (0 === $depth && null !== $this->anonymousGroups) {
            $this->projectedNodes = new \SplObjectStorage();
            $this->projectedItems = 0;
        }
        $projectedContent = $node instanceof \Dom\Element && null !== $this->anonymousGroups && isset($this->anonymousGroups[$node]);
        $style = $node instanceof \Dom\Element ? ($this->styles[$node] ?? []) : array_intersect_key($inherited, array_flip(self::INHERITED_INLINE));
        $style = array_replace($style, $this->usedStyle($node));
        $style = self::inputStyle($style);
        $style = self::decorationStyle($style, $inherited);
        foreach (['--html-control-value', '--html-control-checked', '--html-scroll-x', '--html-scroll-y', '--html-scroll-snap-x', '--html-scroll-snap-y'] as $hint) {
            unset($style[$hint]);
        }
        $state = [];
        if ($node instanceof \Dom\Element) {
            $state = $this->states[$node] ?? [];
            foreach (['value' => '--html-control-value', 'checked' => '--html-control-checked', 'scroll-x' => '--html-scroll-x', 'scroll-y' => '--html-scroll-y', 'scroll-snap-x' => '--html-scroll-snap-x', 'scroll-snap-y' => '--html-scroll-snap-y'] as $key => $hint) {
                if (isset($state[$key])) {
                    $style[$hint] = \is_bool($state[$key]) ? ($state[$key] ? '1' : '0') : (string) $state[$key];
                }
            }
        }
        $display = $node instanceof \Dom\Text ? 'inline' : ($style['display'] ?? 'block');
        if (null !== $this->replacedContentResolver && $node instanceof \Dom\Element && 'none' !== $display && $depth < 512 && !('input' === $node->localName && 'hidden' === strtolower($node->getAttribute('type') ?? ''))) {
            $replacement = ($this->replacedContentResolver)($node, $this->styles[$node] ?? $style);
            if (null !== $replacement) {
                $this->content->enter($style);
                if (null !== $marker = $this->pseudo($node, 'scroll-marker', $style)) {
                    $this->scrollMarkers[$node] = $marker;
                }
                $this->content->leave();
                $topLayer = isset($state['top-layer']) && \is_int($state['top-layer']) ? $state['top-layer'] : null;

                return $this->finish($node, $display, $style, [], null, null, null, $state, $topLayer, $replacement);
            }
        }
        $children = [];
        $generatedBefore = [];
        $entered = $node instanceof \Dom\Element && 'none' !== $display && $depth < 512;
        if ($entered) {
            $this->content->enter($style);
            if (str_contains($display, 'list-item')) {
                $markerStyle = array_replace(array_intersect_key($style, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'visibility', 'direction', 'list-style-position'])), $this->pseudoStyle($node, 'marker'));
                $markerStyle['display'] = 'inline';
                $markerStyle['list-style-position'] ??= 'outside';
                $marker = $this->generated($node, 'marker', $markerStyle, $this->content->marker($node, $style, $this->pseudoStyle($node, 'marker')));
                if (null !== $marker) {
                    $generatedBefore[] = $marker;
                }
            }
            if (null !== $before = $this->pseudo($node, 'before', $style)) {
                $generatedBefore[] = $before;
            }
        }
        $closedDetails = $node instanceof \Dom\Element && 'details' === $node->localName && !$node->hasAttribute('open');
        $summary = $closedDetails ? Details::summary($node) : null;
        if ('none' !== $display && $depth < 512) {
            foreach ($closedDetails || null === $this->anonymousGroups ? $node->childNodes : $this->projectedChildren($node, $style, $depth) as $child) {
                if ($child instanceof Box) {
                    if ('contents' === $child->display) {
                        array_push($children, ...$child->children);
                    } elseif ('none' !== $child->display) {
                        $children[] = $child;
                    }
                    continue;
                }
                if ($closedDetails && $child !== $summary) {
                    continue;
                }
                if ($child instanceof \Dom\Element || $child instanceof \Dom\Text) {
                    $box = $this->build($child, $style, $depth + 1);
                    if ($child instanceof \Dom\Text && '' === $box->text) {
                        continue;
                    }
                    if ('contents' === $box->display) {
                        array_push($children, ...$box->children);
                    } elseif ('none' !== $box->display) {
                        $children[] = $box;
                    }
                }
            }
        }
        $text = $node instanceof \Dom\Text ? $node->textContent : null;
        if ($node instanceof \Dom\Text && !\in_array($style['white-space'] ?? 'normal', ['pre', 'pre-wrap', 'break-spaces'], true)) {
            $text = 'pre-line' === ($style['white-space'] ?? 'normal') ? (preg_replace('/[\t\f ]+/', ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? $text) : (preg_replace('/[\t\r\n\f ]+/', ' ', $text) ?? $text);
            if (!self::inlineNeighbor($node, $this->styles, false)) {
                $text = ltrim($text);
            }
            if (!self::inlineNeighbor($node, $this->styles, true)) {
                $text = rtrim($text);
            }
        }
        $sourceText = $text;
        if ($node instanceof \Dom\Text && null !== $text) {
            $text = \Symfony\Component\HtmlRenderer\Text\TextTransform::apply($text, \Symfony\Component\HtmlRenderer\Text\TextTransform::keywords($style['text-transform'] ?? 'none') ?? []);
        }
        if ($node instanceof \Dom\Element && 'br' === $node->localName) {
            $display = 'line-break';
        }
        $image = null;
        if ($node instanceof \Dom\Element && 'none' !== $display) {
            if ('img' === $node->localName && !$projectedContent) {
                $image = $this->resources->image($node->getAttribute('src') ?? '');
                $text = null === $image ? ($node->getAttribute('alt') ?? '') : null;
            } elseif ('svg' === $node->localName && !$projectedContent) {
                $image = $this->resources->svg($node, fn (\Dom\Element $element): array => $this->styles[$element] ?? []);
            }
            if (null !== $image) {
                $children = [];
            }
            if (null !== $image || 'img' === $node->localName && !$projectedContent) {
                foreach (['width', 'height'] as $dimension) {
                    if (!isset($style[$dimension]) && is_numeric($node->getAttribute($dimension))) {
                        $style[$dimension] = $node->getAttribute($dimension).'px';
                    }
                }
            }
        }
        if ($node instanceof \Dom\Element && \in_array($node->localName, ['input', 'textarea', 'button', 'select'], true) && !$projectedContent) {
            $text = 'button' === $node->localName ? null : ($style['--html-control-value'] ?? \Symfony\Component\HtmlRenderer\Form\ControlValue::displayLabel($node) ?? \Symfony\Component\HtmlRenderer\Form\ControlValue::initial($node));
            $type = strtolower($node->getAttribute('type') ?? 'text');
            if ('input' === $node->localName && 'hidden' === $type) {
                $display = 'none';
            }
            $defaults = ['border-width' => '1px', 'border-style' => 'solid', 'border-color' => 'currentColor', 'padding' => '2px', 'min-height' => '1lh'];
            if ('input' === $node->localName) {
                $defaults += ['white-space' => 'pre', 'overflow' => 'hidden'];
            }
            if ('input' === $node->localName && \in_array($type, ['checkbox', 'radio'], true)) {
                $text = 'none' === ($style['appearance'] ?? 'auto') ? '' : (('1' === ($style['--html-control-checked'] ?? ($node->hasAttribute('checked') ? '1' : '0'))) ? ('checkbox' === $type ? '✓' : '●') : '');
                $defaults += ['width' => '1em', 'height' => '1em'];
            } elseif ('button' !== $node->localName && !\in_array($type, ['submit', 'reset', 'button'], true)) {
                $defaults += ['width' => '20ch'];
            }
            if ('textarea' === $node->localName) {
                $defaults += ['white-space' => 'pre-wrap', 'height' => '2lh'];
            } elseif ('select' === $node->localName) {
                $labels = $state['selection-labels'] ?? array_map(SelectOptions::label(...), SelectOptions::selected($node));
                $text = implode("\n", $labels);
                if ($node->hasAttribute('multiple')) {
                    $defaults += ['white-space' => 'pre-wrap'];
                }
            } elseif ('password' === $type) {
                $text = str_repeat('•', preg_match_all('/\X/u', $text));
            }
            if ('none' === ($style['appearance'] ?? 'auto') && \in_array($type, ['checkbox', 'radio'], true)) {
                unset($defaults['border-width'], $defaults['border-style'], $defaults['border-color'], $defaults['padding']);
            }
            $style = array_replace($defaults, $style);
            if ('button' !== $node->localName) {
                $children = [];
            }
            if ('select' === $node->localName && SelectOptions::isListBox($node)) {
                $text = null;
                $rows = max(1, min(1000, (int) ($node->getAttribute('size') ?? '4')));
                $style += ['height' => $rows.'lh', 'overflow-y' => 'auto'];
                foreach (SelectOptions::options($node) as $option) {
                    $optionStyle = $this->styles[$option] ?? [];
                    if ('none' === ($optionStyle['display'] ?? 'block')) {
                        continue;
                    }
                    $children[] = new Box($option, 'block', $optionStyle, [], SelectOptions::label($option));
                }
            }
        }
        if ($node instanceof \Dom\Element && (\in_array($node->localName, ['progress', 'meter'], true) || 'input' === $node->localName && 'range' === strtolower($node->getAttribute('type') ?? 'text'))) {
            $text = null;
            $children = [];
            $model = match ($node->localName) {
                'progress' => \Symfony\Component\HtmlRenderer\Form\NumericControl::progress($node),
                'meter' => \Symfony\Component\HtmlRenderer\Form\NumericControl::meter($node),
                default => \Symfony\Component\HtmlRenderer\Form\NumericControl::range($node, $state['value'] ?? null),
            };
            $style = array_replace(['width' => '10em', 'height' => '1em', 'position' => 'relative'], $style);
            if ('none' !== ($style['appearance'] ?? 'auto')) {
                $range = 'input' === $node->localName;
                $trackName = $range ? 'track' : 'bar';
                $track = array_replace(['position' => 'absolute', 'left' => '0', 'top' => '25%', 'width' => '100%', 'height' => '50%', 'background-color' => '#808080'], $this->pseudoStyle($node, $trackName));
                $fill = $this->pseudoStyle($node, 'bar-fill');
                $fill = array_replace(['position' => 'absolute', 'left' => '0', 'top' => '25%', 'height' => '50%', 'width' => ($model['fraction'] * 100).'%', 'background-color' => $fill['color'] ?? $style['color'] ?? '#000000'], $fill);
                $children[] = new Box($node, 'block', $track, [], null, null, null, $trackName);
                $children[] = new Box($node, 'block', $fill, [], null, null, null, 'bar-fill');
                if ($range) {
                    $thumb = array_replace(['position' => 'absolute', 'left' => ($model['fraction'] * 100).'%', 'top' => '0', 'width' => '1em', 'height' => '1em', 'transform' => 'translateX(-50%)', 'background-color' => $style['color'] ?? '#000000'], $this->pseudoStyle($node, 'thumb'));
                    $children[] = new Box($node, 'block', $thumb, [], null, null, null, 'thumb');
                }
            }
        }

        if ($entered) {
            if ($closedDetails && null === $summary) {
                $children[] = new Box($node, 'block', $style, [], 'Details', null, null, 'summary');
            }
            $children = [...$generatedBefore, ...$children];
            if (!$projectedContent && 'select' === $node->localName && !SelectOptions::isListBox($node) && null !== $icon = $this->pseudo($node, 'picker-icon', $style)) {
                $children[] = $icon;
            }
            if (null !== $after = $this->pseudo($node, 'after', $style)) {
                $children[] = $after;
            }
            if (null !== $marker = $this->pseudo($node, 'scroll-marker', $style)) {
                $this->scrollMarkers[$node] = $marker;
            }
            $this->content->leave();
        }

        if ('ruby' === $display) {
            $children = $this->ruby($children, $style);
        }

        if ($this->highlights && $node instanceof \Dom\Element && \in_array($display, ['block', 'flow-root', 'list-item', 'table-cell', 'table-caption'], true)) {
            $children = $this->highlight($node, $children);
        }

        if ($node instanceof \Dom\Element && \in_array($display, ['block', 'flow-root', 'list-item', 'table-cell', 'table-caption'], true) && $firstLetter = $this->resolver->getPseudoDeclaredStyle($node, 'first-letter')) {
            $done = false;
            $children = $this->firstLetter($children, self::inputStyle($firstLetter), $done);
        }

        if ($node instanceof \Dom\Element && \in_array($display, ['block', 'flow-root', 'list-item', 'table-cell', 'table-caption'], true)) {
            $firstLine = array_intersect_key(self::inputStyle($this->resolver->getPseudoDeclaredStyle($node, 'first-line')), array_flip(['color', '-webkit-text-fill-color', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness']));
            if ($firstLine) {
                $origin = (string) ++$this->firstLineSequence;
                $style['-renderer-first-line-root'] = $origin;
                $children = $this->firstLine($children, $firstLine, $origin, $this->resolver->getPseudoDeclaredStyle($node, 'first-letter'));
            }
        }

        $selection = $state['selection'] ?? null;
        if ($selection instanceof TextSelection && $node instanceof \Dom\Element) {
            $selectionStyle = $this->pseudoStyle($node, 'selection');
            if ($selectionStyle) {
                $selection = new TextSelection($selection->anchor, $selection->focus, $selection->caretVisible, $selectionStyle['color'] ?? $selection->foreground, $selectionStyle['background-color'] ?? $selectionStyle['background'] ?? $selection->background);
            }
        }

        if ($node instanceof \Dom\Element && \in_array($node->localName, ['input', 'textarea', 'button', 'select'], true) && null !== $text) {
            $textStyle = array_intersect_key($style, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'white-space', 'letter-spacing', 'word-spacing', 'text-align', 'text-align-last', 'text-justify', 'word-break', 'overflow-wrap', 'hyphens', 'hyphenate-character', 'hyphenate-limit-chars', 'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-decoration-thickness', '-webkit-text-fill-color', 'visibility', 'direction']));
            array_splice($children, \count($generatedBefore), 0, [new Box($node, 'inline', $textStyle, [], $text, null, $selection)]);
            if ('input' === $node->localName && isset($state['completion']) && \is_string($state['completion']) && '' !== $state['completion']) {
                $suggestionStyle = self::decorationStyle(array_replace($textStyle, $this->pseudoStyle($node, 'suggestion')), $textStyle);
                if ('none' !== ($suggestionStyle['display'] ?? 'inline')) {
                    array_splice($children, \count($generatedBefore) + 1, 0, [new Box($node, 'inline', $suggestionStyle, [], $state['completion'], null, null, 'suggestion')]);
                }
            }
            if ('' === $text && $node->hasAttribute('placeholder') && \Symfony\Component\HtmlRenderer\Form\ControlValue::supportsPlaceholder($node)) {
                $placeholderStyle = self::decorationStyle(array_replace($textStyle, $this->pseudoStyle($node, 'placeholder')), $textStyle);
                if ('none' !== ($placeholderStyle['display'] ?? 'inline')) {
                    array_splice($children, \count($generatedBefore) + 1, 0, [new Box($node, 'inline', $placeholderStyle, [], $node->getAttribute('placeholder'), null, null, 'placeholder')]);
                }
            }

            $text = null;
            $selection = null;
        }

        if (!$projectedContent && $node instanceof \Dom\Element && 'select' === $node->localName && ($state['picker-open'] ?? false) && isset($state['picker-layer']) && \is_int($state['picker-layer'])) {
            $pickerStyle = array_replace(['display' => 'block', 'position' => 'fixed', 'left' => '0', 'top' => '0', 'width' => 'max-content', 'min-width' => '100%', 'max-height' => '50vh', 'overflow-y' => 'auto', 'border-width' => '1px', 'border-style' => 'solid', 'border-color' => $style['color'] ?? '#000000'], $this->pseudoStyle($node, 'picker'));
            unset($pickerStyle['--html-scroll-y']);
            if (isset($state['picker-scroll-y']) && \is_float($state['picker-scroll-y']) && is_finite($state['picker-scroll-y'])) {
                $pickerStyle['--html-scroll-y'] = (string) max(0.0, $state['picker-scroll-y']);
            }
            if ('none' !== ($pickerStyle['display'] ?? 'block')) {
                $options = [];
                foreach (SelectOptions::options($node) as $option) {
                    $optionStyle = $this->styles[$option] ?? [];
                    if ('none' !== ($optionStyle['display'] ?? 'block')) {
                        $options[] = new Box($option, 'block', $optionStyle, [], SelectOptions::label($option));
                    }
                }
                $children[] = new Box($node, 'block', $pickerStyle, $options, null, null, null, 'picker', $state['picker-layer']);
            }
        }

        if (\in_array($display, ['block', 'flow-root', 'list-item', 'inline-block'], true)) {
            $flow = [];
            foreach ($children as $child) {
                array_push($flow, ...(InlineFormatting::splitBlocks($child, $this->remainingInlineFragments) ?? [$child]));
            }
            $children = $flow;
        }

        $topLayer = $node instanceof \Dom\Element && isset($state['top-layer']) && \is_int($state['top-layer']) ? $state['top-layer'] : null;
        if (null !== $topLayer) {
            $backdrop = $this->pseudoStyle($node, 'backdrop');
            if ('none' !== ($backdrop['display'] ?? 'block')) {
                $backdrop = array_replace($backdrop, ['position' => 'fixed', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0']);
                array_unshift($children, new Box(null, 'block', $backdrop, [], null, null, null, 'backdrop', $topLayer));
            }
        }

        $box = $this->finish($node, $display, $style, $children, $text, $image, $selection, $state, $topLayer);
        if ($node instanceof \Dom\Element && ScrollControls::hostsControls(['display' => $display] + $style)) {
            $buttons = [];
            foreach (['up', 'down', 'left', 'right'] as $direction) {
                if (null !== $button = $this->pseudo($node, 'scroll-button('.$direction.')', $style)) {
                    $buttons[$direction] = $button;
                }
            }
            $group = null;
            $position = $style['scroll-marker-group'] ?? 'none';
            if (\in_array($position, ['before', 'after'], true)) {
                $markers = [];
                foreach ($node->childNodes as $child) {
                    if (isset($this->scrollMarkers[$child])) {
                        $markers[] = $this->scrollMarkers[$child];
                    }
                }
                if ($markers) {
                    $groupStyle = array_replace(array_intersect_key($style, array_flip(self::INHERITED_INLINE)), $this->pseudoStyle($node, 'scroll-marker-group'));
                    $groupStyle = array_replace(['display' => 'flex', 'flex-direction' => 'row', 'flex-wrap' => 'nowrap'], $groupStyle);
                    if ('none' !== $groupStyle['display']) {
                        $group = new Box($node, $groupStyle['display'], $groupStyle, $markers, null, null, null, 'scroll-marker-group');
                    }
                }
            }
            $box = ScrollControls::compose($box, $buttons, $group, $position);
        }
        if ($node instanceof \Dom\Text && null !== $sourceText) {
            $this->sourceTexts[$box] = $sourceText;
        }

        return $box;
    }

    private function finish(\Dom\Node $node, string $display, array $style, array $children, ?string $text, ?ImageResource $image, ?TextSelection $selection, array $state, ?int $topLayer, ?ReplacedContentInterface $replacement = null): Box
    {
        $borderImage = null;
        $borderSource = $style['border-image-source'] ?? 'none';
        $borderSource = \Symfony\Component\HtmlRenderer\Css\ImageSource::imageSet($borderSource)[0] ?? $borderSource;
        if (null !== $borderUrl = \Symfony\Component\HtmlRenderer\Css\ImageSource::url($borderSource)) {
            $borderImage = $this->resources->image($borderUrl);
        }

        $anchor = $state['anchor'] ?? null;
        if ($node instanceof \Dom\Element && \in_array($style['position'] ?? 'static', ['absolute', 'fixed'], true) && str_starts_with($name = trim($style['position-anchor'] ?? ''), '--')) {
            $anchor = $this->namedAnchor($node, $name);
        }
        if (!$anchor instanceof \Dom\Element || !$node instanceof \Dom\Element || $anchor->ownerDocument !== $node->ownerDocument || !$anchor->isConnected) {
            $anchor = null;
        }

        $positionTries = $node instanceof \Dom\Element && \in_array($style['position'] ?? 'static', ['absolute', 'fixed'], true) ? \Symfony\Component\HtmlRenderer\Css\Anchor::alternatives($style, $this->resolver->getPositionTries(), $this->remainingPositionAlternatives) : [];
        $this->remainingPositionAlternatives -= \count($positionTries);
        $references = [];
        if ($node instanceof \Dom\Element && \in_array($style['position'] ?? 'static', ['absolute', 'fixed'], true)) {
            $referenceStyles = [$style];
            foreach ($positionTries as [$alternative]) {
                $referenceStyles[] = $alternative;
            }
            foreach ($referenceStyles as $referenceStyle) {
                foreach (\Symfony\Component\HtmlRenderer\Css\Anchor::references($referenceStyle) as $name) {
                    if (!\array_key_exists($name, $references)) {
                        $references[$name] = $this->namedAnchor($node, $name);
                    }
                }
            }
        }

        return new Box($node, $display, $style, $children, $text, $image, $selection, null, $topLayer, $borderImage, $anchor, $replacement, $references, $positionTries);
    }

    private function ruby(array $children, array $style): array
    {
        $roles = $blank = [];
        foreach ($children as $i => $box) {
            $roles[$i] = $box->display;
            $blank[$i] = null !== $box->text && '' === trim($box->text, " \t\n\r\f");
        }
        $result = [];
        foreach (RubySegments::pair($roles, $blank) as [$bases, $annotations]) {
            foreach ($bases as $index => $base) {
                $own = \count($bases) - 1 === $index ? \array_slice($annotations, $index) : (isset($annotations[$index]) ? [$annotations[$index]] : []);
                $baseBoxes = array_map(static fn (int $id) => $children[$id], $base);
                $annotationBoxes = [];
                foreach ($own as $id) {
                    if ($annotationBoxes) {
                        $annotationBoxes[] = new Box(null, 'inline', $children[$id]->style, [], ' ');
                    }
                    $annotationBoxes[] = $children[$id];
                }
                $pairStyle = array_intersect_key($style, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'ruby-position', 'ruby-align', 'visibility', 'direction']));
                foreach (['ruby-position', 'ruby-align'] as $property) {
                    if ($own && isset($children[$own[0]]->style[$property])) {
                        $pairStyle[$property] = $children[$own[0]]->style[$property];
                    }
                }
                $parts = [new Box(null, 'ruby-base', $pairStyle, $baseBoxes)];
                if ($annotationBoxes) {
                    $annotationStyle = array_replace($pairStyle, array_intersect_key($annotationBoxes[0]->style, array_flip(['font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'color'])));
                    $parts[] = new Box(null, 'ruby-text', $annotationStyle, $annotationBoxes);
                }
                $result[] = new Box(null, 'ruby-pair', $pairStyle, $parts);
            }
        }

        return $result;
    }

    private function highlight(\Dom\Element $element, array $children): array
    {
        $source = '';
        $texts = [];
        $collect = function (array $boxes) use (&$collect, &$source, &$texts): void {
            foreach ($boxes as $box) {
                if ('line-break' === $box->display) {
                    $source .= "\n";
                } elseif (null === $box->pseudoElement && null === $box->topLayer && \in_array($box->display, ['inline', 'contents'], true)) {
                    if (isset($this->sourceTexts[$box])) {
                        $text = $this->sourceTexts[$box];
                        $texts[] = [$box, $text, \strlen($source)];
                        $source .= $text;
                    } else {
                        $collect($box->children);
                    }
                } else {
                    $source .= "\0";
                }
            }
        };
        $collect($children);
        $rules = [];
        foreach ($this->highlights as $name => [$search, $matchCase]) {
            if ($style = \Symfony\Component\HtmlRenderer\Text\HighlightRanges::declarations(self::inputStyle($this->resolver->getPseudoDeclaredStyle($element, 'highlight('.$name.')')))) {
                $rules[] = [\Symfony\Component\HtmlRenderer\Text\HighlightRanges::pattern($search, $matchCase), $style];
            }
        }
        if (!$rules) {
            return $children;
        }
        $pieces = \Symfony\Component\HtmlRenderer\Text\HighlightRanges::pieces($source, $texts, $rules);
        $replace = function (array $boxes) use (&$replace, $pieces): array {
            $result = [];
            foreach ($boxes as $box) {
                if (isset($pieces[$box])) {
                    $offset = 0;
                    $raw = $this->sourceTexts[$box];
                    $keywords = \Symfony\Component\HtmlRenderer\Text\TextTransform::keywords($box->style['text-transform'] ?? 'none') ?? [];
                    foreach ($pieces[$box] as [$piece, $marks]) {
                        $start = $keywords ? \strlen(\Symfony\Component\HtmlRenderer\Text\TextTransform::apply(substr($raw, 0, $offset), $keywords)) : $offset;
                        $offset += \strlen($piece);
                        $end = $keywords ? \strlen(\Symfony\Component\HtmlRenderer\Text\TextTransform::apply(substr($raw, 0, $offset), $keywords)) : $offset;
                        $style = $box->style;
                        foreach ($marks as $mark) {
                            $style = array_replace($style, $mark);
                            foreach (['color', '-webkit-text-fill-color'] as $property) {
                                if (isset($mark[$property])) {
                                    $style['-renderer-highlight-'.$property] = '1';
                                }
                            }
                        }
                        if ($marks) {
                            $style['-renderer-highlight'] = '1';
                        }
                        $result[] = new Box($box->node, $box->display, $style, [], substr($box->text, $start, $end - $start), $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
                    }
                } else {
                    $result[] = new Box($box->node, $box->display, $box->style, $replace($box->children), $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
                }
            }

            return $result;
        };

        return $replace($children);
    }

    private function firstLine(array $children, array $candidates, string $origin, array $firstLetter): array
    {
        $result = [];
        foreach ($children as $box) {
            if (null !== $box->topLayer || \in_array($box->style['position'] ?? 'static', ['absolute', 'fixed'], true) || 'marker' === $box->pseudoElement || !\in_array($box->display, ['inline', 'contents'], true)) {
                $result[] = $box;
                continue;
            }
            $own = $candidates;
            $specified = 'first-letter' === $box->pseudoElement ? $firstLetter : ($box->node instanceof \Dom\Element && null === $box->pseudoElement ? $this->resolver->getCascadeRecord($box->node)['specified'] : []);
            if ($box->node instanceof \Dom\Element && \in_array($box->pseudoElement, ['before', 'after'], true)) {
                $specified = $this->resolver->getPseudoDeclaredStyle($box->node, $box->pseudoElement);
            }
            foreach (['color', '-webkit-text-fill-color'] as $property) {
                if (isset($specified[$property]) || isset($box->style['-renderer-highlight-'.$property])) {
                    unset($own[$property]);
                }
            }
            $style = $box->style;
            if (null !== $box->text) {
                $style['-renderer-first-line-origin'] = $origin;
                foreach ($own as $property => $value) {
                    $style['-renderer-first-line-'.$property] = $value;
                }
            }
            $result[] = new Box($box->node, $box->display, $style, $this->firstLine($box->children, $own, $origin, $firstLetter), $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
        }

        return $result;
    }

    private function firstLetter(array $children, array $style, bool &$done): array
    {
        $result = [];
        foreach ($children as $box) {
            if ($done || null !== $box->topLayer || \in_array($box->style['position'] ?? 'static', ['absolute', 'fixed'], true) || 'marker' === $box->pseudoElement) {
                $result[] = $box;
                continue;
            }
            if (($box->node instanceof \Dom\Text || \in_array($box->pseudoElement, ['before', 'after'], true)) && null !== $box->text && '' !== trim($box->text) && null !== $parts = \Symfony\Component\HtmlRenderer\Text\InitialLetter::split($box->text)) {
                [$before,$letter,$after] = $parts;
                if ('' !== $before) {
                    $result[] = new Box($box->node, 'inline', $box->style, [], $before, null, null, $box->pseudoElement);
                }
                $result[] = new Box($box->node, 'inline', self::decorationStyle(array_replace($box->style, $style), $box->style), [], $letter, null, null, 'first-letter');
                if ('' !== $after) {
                    $result[] = new Box($box->node, 'inline', $box->style, [], $after, null, null, $box->pseudoElement);
                }
                $done = true;
                continue;
            }
            if (\in_array($box->display, ['inline', 'contents'], true) && $box->children) {
                $nested = $this->firstLetter($box->children, $style, $done);
                $result[] = new Box($box->node, $box->display, $box->style, $nested, $box->text, $box->image, $box->selection, $box->pseudoElement, $box->topLayer, $box->borderImage, $box->anchor, $box->replacedContent, $box->anchorReferences, $box->positionTries);
            } else {
                $result[] = $box;
                if (null !== $box->image || !\in_array($box->display, ['inline', 'contents'], true)) {
                    $done = true;
                }
            }
        }

        return $result;
    }

    private static function decorationStyle(array $style, array $inherited): array
    {
        $ancestorLines = array_filter(explode(' ', $inherited['text-decoration-line'] ?? ''), static fn (string $line): bool => !\in_array($line, ['', 'none'], true));
        $ownLines = array_filter(explode(' ', $style['text-decoration-line'] ?? ''), static fn (string $line): bool => !\in_array($line, ['', 'none'], true));
        if ($ancestorLines || $ownLines) {
            if (!$ownLines) {
                foreach (['style', 'color', 'thickness'] as $part) {
                    if (isset($inherited['text-decoration-'.$part])) {
                        $style['text-decoration-'.$part] = $inherited['text-decoration-'.$part];
                    }
                }
            }
            $style['text-decoration-line'] = implode(' ', array_unique([...$ancestorLines, ...$ownLines]));
            $style['text-decoration-color'] ??= $style['color'] ?? '#000000';
        }

        return $style;
    }

    private static function inputStyle(array $style): array
    {
        return array_filter($style, static fn (string $property): bool => !str_starts_with($property, '-renderer-'), \ARRAY_FILTER_USE_KEY);
    }

    private function pseudoStyle(\Dom\Element $element, string $name): array
    {
        $style = self::inputStyle($this->resolver->getPseudoStyle($element, $name));
        $principal = $this->usedStyle($element);
        if (isset($principal['color']) && !\array_key_exists('color', $this->resolver->getCascadeRecord($element)['pseudos'][$name]['declarations'] ?? [])) {
            $style['color'] = $principal['color'];
        }

        return array_replace($style, $this->usedStyle($element, $name));
    }

    private function namedAnchor(\Dom\Element $target, string $name): ?\Dom\Element
    {
        if (null === $this->namedAnchors) {
            $this->namedAnchors = [];
            $entries = 0;
            foreach ($this->styles as $node) {
                if (!$node instanceof \Dom\Element || $node->ownerDocument !== $target->ownerDocument || !$node->isConnected || \in_array(($this->styles[$node] ?? [])['display'] ?? 'inline', ['none', 'contents'], true)) {
                    continue;
                }
                foreach (preg_split('/[\s,]+/', trim(($this->styles[$node] ?? [])['anchor-name'] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $anchorName) {
                    if (!str_starts_with($anchorName, '--') || null === $scope = $this->anchorScope($node, $anchorName)) {
                        continue;
                    }
                    $id = spl_object_id($node);
                    if (isset($this->namedAnchors[$anchorName][$scope]['ids'][$id])) {
                        continue;
                    }
                    if (++$entries > 100000) {
                        throw new \LengthException('Named anchors exceed the document index limit.');
                    }
                    $this->namedAnchors[$anchorName][$scope] ??= ['flow' => [], 'positioned' => [], 'ids' => []];
                    $kind = \in_array(($this->styles[$node] ?? [])['position'] ?? 'static', ['absolute', 'fixed'], true) ? 'positioned' : 'flow';
                    $this->namedAnchors[$anchorName][$scope][$kind][] = $node;
                    $this->namedAnchors[$anchorName][$scope]['ids'][$id] = true;
                }
            }
            foreach (array_keys($this->namedAnchors) as $anchorName) {
                foreach (array_keys($this->namedAnchors[$anchorName]) as $scope) {
                    foreach (['flow', 'positioned'] as $kind) {
                        usort($this->namedAnchors[$anchorName][$scope][$kind], static fn (\Dom\Element $a, \Dom\Element $b): int => $a === $b ? 0 : (($a->compareDocumentPosition($b) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING) ? -1 : 1));
                    }
                }
            }
        }
        $scope = $this->anchorScope($target, $name);
        $index = null !== $scope ? ($this->namedAnchors[$name][$scope] ?? null) : null;
        if (null === $index) {
            return null;
        }
        // CSS chooses the nearest eligible ancestor, otherwise the last eligible name in tree order.
        for ($ancestor = $target->parentElement; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if (isset($index['ids'][spl_object_id($ancestor)])) {
                return $ancestor;
            }
        }
        $flow = $index['flow'] ? $index['flow'][array_key_last($index['flow'])] : null;
        if (null !== $flow && $target->contains($flow)) {
            $flow = self::precedingAnchor($index['flow'], $target);
        }
        $positioned = self::precedingAnchor($index['positioned'], $target);

        return null === $flow ? $positioned : (null !== $positioned && ($flow->compareDocumentPosition($positioned) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING) ? $positioned : $flow);
    }

    private function anchorScope(\Dom\Element $element, string $name): ?string
    {
        $tree = $element->getAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE);
        $scope = 0;
        for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            $style = $this->styles[$ancestor] ?? [];
            if ('hidden' === ($style['content-visibility'] ?? 'visible') || 'none' === ($style['display'] ?? 'inline')) {
                return null;
            }
            if (0 !== $scope || $tree !== $ancestor->getAttribute(\Symfony\Component\HtmlRenderer\Css\Selector::TREE_ATTRIBUTE)) {
                continue;
            }
            $names = preg_split('/[\s,]+/', trim($style['anchor-scope'] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            if (\in_array('all', $names, true) || \in_array($name, $names, true)) {
                $scope = spl_object_id($ancestor);
            }
        }

        return ($tree ?? '').'|'.$scope;
    }

    /** @param list<\Dom\Element> $candidates */
    private static function precedingAnchor(array $candidates, \Dom\Element $target): ?\Dom\Element
    {
        $low = 0;
        $high = \count($candidates);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($candidates[$middle] !== $target && ($candidates[$middle]->compareDocumentPosition($target) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING)) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low > 0 ? $candidates[$low - 1] : null;
    }

    private function usedStyle(\Dom\Node $node, string $role = ''): array
    {
        $style = ($this->usedStyles[$node] ?? [])[$role] ?? [];
        foreach ($style as $property => $value) {
            if (!\is_string($property) || !\is_string($value)) {
                throw new \InvalidArgumentException('Used presentation styles must contain string declarations.');
            }
        }

        return $style;
    }

    private function pseudo(\Dom\Element $element, string $name, array $inherited): ?Box
    {
        $style = self::decorationStyle($this->pseudoStyle($element, $name), $inherited);
        if ('q' === $element->localName && \in_array($name, ['before', 'after'], true) && !\array_key_exists('content', $style)) {
            $style = array_replace(array_intersect_key($inherited, array_flip(['color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'quotes'])), $style, ['content' => 'before' === $name ? 'open-quote' : 'close-quote']);
        }
        if ('none' === ($style['display'] ?? 'inline')) {
            return null;
        }

        return $this->generated($element, $name, $style, $this->content->parts($element, $style));
    }

    private function generated(\Dom\Element $element, string $name, array $style, ?array $parts): ?Box
    {
        if (null === $parts) {
            return null;
        }
        $children = [];
        foreach ($parts as $part) {
            if (isset($part['url'])) {
                if (null !== $image = $this->resources->image($part['url'])) {
                    $children[] = new Box($element, 'inline-block', $style, [], null, $image, null, $name);
                }
            } else {
                $children[] = new Box($element, 'inline', $style, [], \Symfony\Component\HtmlRenderer\Text\TextTransform::apply($part['text'], \Symfony\Component\HtmlRenderer\Text\TextTransform::keywords($style['text-transform'] ?? 'none') ?? []), null, null, $name);
            }
        }
        if (1 === \count($children)) {
            $child = $children[0];

            return new Box($element, $style['display'] ?? 'inline', $style, [], $child->text, $child->image, null, $name);
        }

        return new Box($element, $style['display'] ?? 'inline', $style, $children, null, null, null, $name);
    }

    private function projectedChildren(\Dom\Node $owner, array $style, int $depth): iterable
    {
        if (!isset($this->anonymousGroups[$owner])) {
            yield from iterator_to_array($owner->childNodes, false);

            return;
        }
        yield from $this->projectedItems($this->anonymousGroups[$owner], $owner, $style, $depth);
    }

    private function projectedItems(array $items, \Dom\Node $owner, array $inherited, int $depth): iterable
    {
        if ($depth >= 512) {
            throw new \InvalidArgumentException('Anonymous presentation nesting exceeds the box depth limit.');
        }
        $this->projectedNodes ??= new \SplObjectStorage();
        foreach ($items as $item) {
            if (++$this->projectedItems > 100000) {
                throw new \LengthException('Anonymous presentation exceeds the box count limit.');
            }
            if ($item instanceof \Dom\Node) {
                if ($item->parentNode !== $owner || isset($this->projectedNodes[$item])) {
                    throw new \InvalidArgumentException('Anonymous presentation must reference each semantic child at most once in its native parent.');
                }
                $this->projectedNodes[$item] = true;
                yield $item;
                continue;
            }
            if (!\is_array($item) || !isset($item['style'], $item['children']) || !\is_array($item['style']) || !\is_array($item['children'])) {
                throw new \InvalidArgumentException('Anonymous presentation requires style and children arrays.');
            }
            foreach ($item['style'] as $property => $value) {
                if (!\is_string($property) || !\is_string($value)) {
                    throw new \InvalidArgumentException('Anonymous presentation styles must contain string declarations.');
                }
            }
            $style = array_replace(array_intersect_key($inherited, array_flip(self::INHERITED_INLINE)), $item['style']);
            if ('none' === ($style['display'] ?? 'block')) {
                continue;
            }
            $children = [];
            foreach ($this->projectedItems($item['children'], $owner, $style, $depth + 1) as $child) {
                $box = $child instanceof Box ? $child : $this->build($child, $style, $depth + 1);
                if ('contents' === $box->display) {
                    array_push($children, ...$box->children);
                } elseif ('none' !== $box->display) {
                    $children[] = $box;
                }
            }
            yield new Box(null, $style['display'] ?? 'block', $style, $children);
        }
    }

    /** @param \SplObjectStorage<\Dom\Element, array<string, string>> $styles */
    private static function inlineNeighbor(\Dom\Node $node, \SplObjectStorage $styles, bool $after): bool
    {
        $sibling = $after ? $node->nextSibling : $node->previousSibling;
        while (null !== $sibling) {
            if ($sibling instanceof \Dom\Text && '' !== trim($sibling->textContent)) {
                return true;
            }
            if ($sibling instanceof \Dom\Element && 'none' !== (($styles[$sibling] ?? [])['display'] ?? 'inline')) {
                return \in_array(($styles[$sibling] ?? [])['display'] ?? 'inline', ['inline', 'inline-block', 'inline-flex', 'inline-grid', 'contents'], true);
            }
            $sibling = $after ? $sibling->nextSibling : $sibling->previousSibling;
        }
        $parent = $node->parentElement;

        return null !== $parent && \in_array(($styles[$parent] ?? [])['display'] ?? 'block', ['inline', 'contents'], true) && self::inlineNeighbor($parent, $styles, $after);
    }
}
