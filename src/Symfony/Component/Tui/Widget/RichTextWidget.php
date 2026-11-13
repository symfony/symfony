<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Event\LiveRegionChangedEvent;
use Symfony\Component\Tui\Render\Compositor;
use Symfony\Component\Tui\Render\Layer;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VectorImage;
use Symfony\Component\Tui\Widget\Figlet\FigletRenderer;
use Symfony\Component\Tui\Widget\Text\Hyphenator;
use Symfony\Component\Tui\Widget\Text\InlineContentInterface;
use Symfony\Component\Tui\Widget\Text\InlineFormat;
use Symfony\Component\Tui\Widget\Text\InlineImages;
use Symfony\Component\Tui\Widget\Text\InlineNode;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\Tui\Widget\Util\UrlResolver;

/**
 * A text block made of inline nodes: runs of text and inline elements.
 *
 * The formatting of each inline element (its style, generated content,
 * text transform, whitespace handling) is set from the outside, usually by
 * a CSS stylesheet, so that `p strong { color: red }` or
 * `li::before { content: "→ " }` apply.
 *
 * Links (<a href>) become terminal hyperlinks (OSC 8). Links to a fragment
 * of the page (#id) are marked for the renderer, which strips the marks and
 * keeps where the links are: a click on one goes there, see HtmlView::navigate().
 *
 * @experimental
 */
class RichTextWidget extends TextWidget implements InlineContentInterface
{
    /** Whether the lines drawn keep their no-break spaces, see keepNoBreakSpaces() */
    private bool $keepNoBreakSpaces = false;
    private const LINK_SCHEMES = '~^(?:https?|mailto|file|ftp|sftp|ssh):~i';

    /** The most images drawn in a text, see setInlineImages(), and as many of its generated content, see setGeneratedImages() */
    public const MAX_INLINE_IMAGES = InlineImages::MAX;

    /** @var list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> */
    private array $inlineImages = [];
    /** @var list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> */
    private array $generatedImages = [];
    private ?InlineImages $drawnImages = null;

    /**
     * The parameters of the OSC 8 sequences marking a link to a fragment: the renderer takes them out of the frame.
     *
     * @internal
     */
    public const FRAGMENT_LINK = 'tui-fragment';

    /**
     * The parameters of the OSC 8 sequences marking a link the terminal cannot follow, like a relative one without a
     * base URL: the renderer takes them out of the frame, keeping where the link is, for :hover.
     */
    public const LINK_MARK = 'tui-link';

    private InlineFormat $block;

    /** @var array<int, InlineFormat> */
    private array $formats = [];

    private ?string $link = null;

    /** The href of the whole block, see setLink(), as given */
    private ?string $blockHref = null;
    private ?string $baseUrl = null;

    /** The link that has the focus, see getLinks(): reversed, the cursor on it */
    private ?int $currentLink = null;
    private ?int $hoveredLink = null;
    /** The columns of the first line while it is rendered again with ::first-line, -1 otherwise */
    private int $firstLineColumns = -1;
    /** The first letter drawn as a drop cap, like initial-letter, kept out of the text when it is composed */
    private ?string $dropCap = null;

    /** The OSC code of the markers of where a cloned box starts (o) and ends (c), drawn nowhere */
    private const CLONE_CODE = '5113';
    private const CLONE_MARKER = "\x1b]5113;%s%d\x07";

    /** @var list<array{string, string}> The inline boxes cloned on each of their lines, like box-decoration-break: clone: their left and right decorations */
    private array $clones = [];

    /** The word joiner kept between the characters of a ruby base, which the lines do not break at, drawn nowhere */
    private const WORD_JOINER = "\u{2060}";
    /** A letter of Chinese, Japanese or Korean, which word-break: keep-all keeps with those around it */
    private const CJK_LETTER = '[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Bopomofo}\x{30FC}]';

    /** @var list<array{string, bool, bool}> The annotations of the ruby bases marked in the text (r and R): as wide as their base, whether under it, and whether an emphasis mark, which is not read */
    private array $rubies = [];

    /** @var array<int, array{base: int, row: string, segments: list<array{int, string}>}> The rows of the annotations last rendered, by row: the row of their bases, what was drawn, and the column of each annotation */
    private array $annotationRows = [];

    /** The patterns that break the words of the element being composed, like hyphens: auto */
    private ?Hyphenator $hyphenator = null;
    /** The format whose text-emphasis marks the text being composed, see emphasize() */
    private ?InlineFormat $emphasis = null;

    /** @var array{list<string>, int}|null The rows of a drop cap and its width, drawn beside the annotations of ruby */
    private ?array $capRows = null;

    /** @var list<int> See getLineStarts() */
    private array $lineStarts = [];

    /**
     * @param list<InlineNode>          $nodes
     * @param 'polite'|'assertive'|null $politeness Announce content changes, like an ARIA live region
     */
    public function __construct(
        private array $nodes = [],
        private readonly bool $truncateLines = false,
        private readonly ?string $politeness = null,
    ) {
        $this->block = new InlineFormat();
        parent::__construct($this->compose(), $truncateLines);
    }

    /**
     * @return 'polite'|'assertive'|null
     */
    public function getPoliteness(): ?string
    {
        return $this->politeness;
    }

    public function getInlineNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @param list<InlineNode> $nodes
     *
     * @return $this
     */
    public function setInlineNodes(array $nodes): static
    {
        $previous = $this->getPlainText();
        $this->nodes = $nodes;
        $this->refresh();

        if (null !== $this->politeness && $previous !== $text = $this->getPlainText()) {
            $this->dispatch(new LiveRegionChangedEvent($this, $previous, $text, $this->politeness));
        }

        return $this;
    }

    /**
     * Replaces the content with plain text.
     */
    public function setText(string $text): static
    {
        return $this->setInlineNodes([InlineNode::text($text)]);
    }

    public function setInlineFormats(InlineFormat $block, array $nodes): void
    {
        // The same formats pushed again, like by a stylesheet restyling the page: nothing to draw again
        if ($block->equals($this->block) && array_keys($nodes) === array_keys($this->formats) && array_all($nodes, fn (InlineFormat $format, int $index) => $format->equals($this->formats[$index]))) {
            return;
        }

        $this->block = $block;
        $this->formats = $nodes;
        $this->refresh();
    }

    /**
     * Makes the whole block a terminal hyperlink, like a link laid out as a block.
     *
     * Only web, mail, file and remote shell targets are kept.
     *
     * @return $this
     */
    public function setLink(?string $href): static
    {
        $this->blockHref = null !== $href && '' !== trim($href) ? trim($href) : null;
        $link = null !== $href ? self::linkTarget($this->resolve($href)) : null;
        if ($link !== $this->link) {
            $this->link = $link;
            $this->refresh();
        }

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * The URL relative links are resolved against, like the <base href> of a document; links to a fragment (#id)
     * stay in the page.
     *
     * @return $this
     */
    public function setBaseUrl(?string $baseUrl): static
    {
        if ($baseUrl !== $this->baseUrl) {
            $this->baseUrl = $baseUrl;
            $this->link = null !== $this->blockHref ? self::linkTarget($this->resolve($this->blockHref)) : null;
            $this->refresh();
        }

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * The marker drawn before the first line of a list item, outside its text, null without one.
     */
    public function getMarker(): ?string
    {
        return $this->block->marker;
    }

    /**
     * The targets of the links of the text, in order: its <a href> elements, else the link of the whole block.
     *
     * @return list<string>
     */
    public function getLinks(): array
    {
        $links = self::linksOf($this->nodes);
        $links = $links || null === $this->blockHref ? $links : [$this->blockHref];

        return null !== $this->baseUrl ? array_map($this->resolve(...), $links) : $links;
    }

    /**
     * The targets of the <a href> elements among inline nodes, in order.
     *
     * @param list<InlineNode> $nodes
     *
     * @return list<string>
     *
     * @internal
     */
    public static function linksOf(array $nodes): array
    {
        $links = [];
        foreach ($nodes as $node) {
            if ($node->isText()) {
                continue;
            }
            if ('a' === $node->tagName && '' !== $href = trim($node->attributes['href'] ?? '')) {
                $links[] = $href;
            }
            array_push($links, ...self::linksOf($node->children));
        }

        return $links;
    }

    /**
     * Shows a link as the one with the focus, see getLinks(), or none.
     */
    protected function setCurrentLink(?int $index): void
    {
        if ($index !== $this->currentLink) {
            $this->currentLink = $index;
            $this->refresh();
        }
    }

    /**
     * Shows a link as the one under the pointer, by its index among getLinks(), or none: it matches :hover.
     */
    public function setHoveredLink(?int $index): void
    {
        if ($index !== $this->hoveredLink) {
            $this->hoveredLink = $index;
            // Its styles come from the cascade, which matches it again
            $this->invalidate();
        }
    }

    public function getHoveredLink(): ?int
    {
        return $this->hoveredLink;
    }

    /**
     * The id of the link of an index in the frame, as the OSC 8 id of the terminal link: see Tui::getLinkAt().
     */
    public static function linkId(self $widget, int $index): string
    {
        return 'tui-link-'.spl_object_id($widget).'-'.$index;
    }

    /**
     * The link shown as the one with the focus, by its index among getLinks(), null for none.
     */
    public function getCurrentLink(): ?int
    {
        return $this->currentLink;
    }

    /**
     * Sets the images drawn in the text, each standing for a character of a private use plane in it per cell, see
     * inlineImageText(): its image, its columns, and its text alternative for the plain text; drawn a row tall, in
     * half blocks.
     *
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $images
     */
    public function setInlineImages(array $images): void
    {
        $images = \array_slice(array_values($images), 0, self::MAX_INLINE_IMAGES);
        if ($images !== $this->inlineImages) {
            $this->inlineImages = $images;
            $this->drawnImages = null;
            $this->invalidate();
        }
    }

    /**
     * Sets the images of the generated content of the text, like content: url() on ::before, drawn like those of
     * setInlineImages(), by the characters of generatedImageText().
     *
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $images
     */
    public function setGeneratedImages(array $images): void
    {
        $images = \array_slice(array_values($images), 0, self::MAX_INLINE_IMAGES);
        if ($images !== $this->generatedImages) {
            $this->generatedImages = $images;
            $this->drawnImages = null;
            $this->invalidate();
        }
    }

    /**
     * The characters standing for the cells of an image drawn in the text, see setInlineImages(): one per column, of
     * a private use plane, that the cells of the image take the place of when it is drawn.
     */
    public static function inlineImageText(int $index, int $columns, bool $generated = false): string
    {
        return InlineImages::text($index, $columns, $generated);
    }

    private function images(): InlineImages
    {
        return $this->drawnImages ??= new InlineImages($this->inlineImages, $this->generatedImages);
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $lines = $this->renderShadowed($context);

        return $this->inlineImages || $this->generatedImages ? $this->images()->draw($lines, $this->getContext()?->getMediaContext()->colorScheme) : $lines;
    }

    /**
     * @return string[]
     */
    private function renderShadowed(RenderContext $context): array
    {
        if (!$shadows = $this->block->textShadow) {
            return $this->renderInsetLines($context);
        }
        // How far the shadows reach past the text on each side
        $left = max(0, -min(array_column($shadows, 0)));
        $right = max(0, max(array_column($shadows, 0)));
        $top = max(0, -min(array_column($shadows, 1)));
        $bottom = max(0, max(array_column($shadows, 1)));
        // Without the room for the text and the shadows beside it, the text alone
        if ($context->getColumns() <= $left + $right) {
            return $this->renderInsetLines($context);
        }

        // text-shadow: the text in the color of each shadow, moved, under the text, showing in the cells it leaves
        // blank, the first shadow over the others; the text as much narrower as they move it sideways
        $columns = $context->getColumns() - $left - $right;
        $lines = $this->renderInsetLines($context->withColumns($columns));
        // Lines the layers would not keep, those of text drawn larger, go without a shadow
        if (!$lines || str_contains(str_replace(AnsiUtils::CURSOR_MARKER_PREFIX, '', implode('', $lines)), "\x1b_")) {
            return $lines;
        }
        // Its images not in the shadows, but in the text over them
        $plain = array_map(static fn (string $line) => InlineImages::blank(AnsiUtils::stripAnsiCodes($line)), $lines);
        if ($this->inlineImages || $this->generatedImages) {
            $lines = $this->images()->draw($lines, $this->getContext()?->getMediaContext()->colorScheme);
        }
        // The cursor of a focused link, which the layers do not keep: put back at its column
        $markers = [];
        foreach ($lines as $i => $line) {
            if (false !== $at = strpos($line, AnsiUtils::CURSOR_MARKER_PREFIX)) {
                $end = strpos($line, "\x07", $at);
                $markers[$i] = [AnsiUtils::visibleWidth(substr($line, 0, $at)), substr($line, $at, false === $end ? null : $end + 1 - $at)];
            }
        }
        $width = $columns + $left + $right;
        $height = \count($lines) + $top + $bottom;
        $layers = [new Layer([''], width: $width, height: $height)];
        // The last shadow at the bottom, the first one over the others, their blank cells showing what is under them
        foreach (array_reverse($shadows) as [$x, $y, $color]) {
            $style = null !== $color ? new Style(color: $color) : new Style(dim: true);
            $layers[] = new Layer(array_map($style->apply(...), $plain), $top + $y, $left + $x, true);
        }
        // Each line hides the shadows over its span, from its first glyph to its last, so that they peek out around the
        // text, not through the spaces between its words
        foreach ($lines as $i => $line) {
            $text = rtrim(AnsiUtils::stripAnsiCodes($line), ' ');
            if ('' === $trimmed = ltrim($text, ' ')) {
                continue;
            }
            $lead = \strlen($text) - \strlen($trimmed);
            $layers[] = new Layer([0 === $lead ? $line : AnsiUtils::splitAround($line, 0, $lead)[1]], $i + $top, $lead + $left);
        }
        $composed = Compositor::composite(...$layers);
        foreach ($markers as $i => [$column, $marker]) {
            [$before, $after] = AnsiUtils::splitAround($composed[$i + $top], $column + $left, 0);
            $composed[$i + $top] = $before.$marker.$after;
        }

        return array_map('rtrim', $composed);
    }

    /**
     * @return list<string>
     */
    private function renderInsetLines(RenderContext $context): array
    {
        $lines = $this->renderLines($context);
        $rowInsets = $this->getLineInsets();
        if ([] === $rowInsets || ([] === $this->annotationRows && 0 === $this->block->lineGap)) {
            return $lines;
        }

        // Beside floats, the insets are those of the rows: each line takes those of the row it is drawn on, below the
        // annotations and the blank rows of line-height before it, found again until they are the same
        $insets = $rowInsets;
        try {
            for ($pass = 0; $pass < 3; ++$pass) {
                $next = [];
                foreach ($this->lineStarts as $start) {
                    $row = isset($this->annotationRows[$start]) && $this->annotationRows[$start]['base'] > $start ? $start + 1 : $start;
                    $next[] = $rowInsets[min($row, \count($rowInsets) - 1)];
                }
                $next[] = end($rowInsets);
                if ($next === $insets) {
                    break;
                }
                $insets = $next;
                $this->setLineInsets($insets);
                $lines = $this->renderLines($context);
            }
        } finally {
            $this->setLineInsets($rowInsets);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderLines(RenderContext $context): array
    {
        $this->annotationRows = [];
        $this->capRows = null;
        $lines = $this->renderBlock($context);
        if (str_contains(implode('', $lines), "\x1b]".self::CLONE_CODE.';o')) {
            // box-decoration-break: clone, a box that wraps has its decorations on each of its lines: the room for them
            // kept, and found again, until it is the same
            $base = $this->getLineInsets();
            try {
                for ($pass = 0; $pass < 3; ++$pass) {
                    $insets = $this->cloneInsets($lines, $base, $context->getColumns());
                    if ($insets === $this->getLineInsets()) {
                        break;
                    }
                    $this->setLineInsets($insets);
                    $lines = $this->renderBlock($context);
                }
            } finally {
                $this->setLineInsets($base);
            }
        }
        // The bidirectional algorithm: each line in visual order, before the annotations are put over their bases
        if (Bidi::isAvailable()) {
            $lines = $this->reorder($lines);
        }
        if ([] !== $this->rubies) {
            $lines = $this->placeRubies($lines);
        }
        $this->lineStarts = $this->lineStartsOf(\count($lines));

        if (str_contains($joined = implode('', $lines), "\x1b]".self::CLONE_CODE.';')) {
            // The markers drawn nowhere
            $lines = array_map(static fn (string $line): string => preg_replace('/\x1b\]'.self::CLONE_CODE.';[oc]\d+\x07/', '', $line) ?? $line, $lines);
        }
        // The no-break spaces that kept words together, like those of <nobr> or the padding of a cloned box, are spaces
        // once laid out
        if (!$this->keepNoBreakSpaces && (str_contains($joined, "\u{00A0}") || str_contains($joined, self::WORD_JOINER))) {
            $lines = array_map(static fn (string $line): string => str_replace(["\u{00A0}", self::WORD_JOINER], [' ', ''], $line), $lines);
        }

        return $lines;
    }

    /**
     * The rows of the lines last rendered where each line starts, an annotation over it included, and the blank rows
     * of line-height before it: where a multi-column container can cut the text.
     *
     * @return list<int>
     *
     * @internal
     */
    public function getLineStarts(): array
    {
        return $this->lineStarts;
    }

    /**
     * @return array{int, int} The fewest lines before a column break and after it, like orphans and widows
     *
     * @internal
     */
    public function getOrphansAndWidows(): array
    {
        return [$this->block->orphans, $this->block->widows];
    }

    /**
     * @return list<int>
     */
    private function lineStartsOf(int $count): array
    {
        $stride = 1 + $this->block->lineGap;
        $starts = [];
        $line = 0;
        for ($row = 0; $row < $count; ++$row) {
            if (isset($this->annotationRows[$row])) {
                // Over its base, the annotation starts its line; under it, it ends it
                if ($this->annotationRows[$row]['base'] > $row && 0 === $line % $stride) {
                    $starts[] = $row;
                }
                continue;
            }
            $annotated = isset($this->annotationRows[$row - 1]) && $row === $this->annotationRows[$row - 1]['base'];
            if (0 === $line % $stride && !$annotated) {
                $starts[] = $row;
            }
            ++$line;
        }

        return $starts;
    }

    /**
     * The lines in visual order, in a paragraph of the direction of the block (or of its first strong character, like
     * unicode-bidi: plaintext), the embeddings and isolates open at the end of a line opened again on the next.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function reorder(array $lines): array
    {
        $level = $this->block->rtl ? 1 : 0;
        if ('plaintext' === $this->block->unicodeBidi) {
            $level = Bidi::direction($this->getText()) ?? $level;
        }
        $open = '';
        foreach ($lines as $i => $line) {
            $text = $open.$line;
            $open = Bidi::openControls($text);
            if ('' !== $line && Bidi::needsReordering($text, $level)) {
                $lines[$i] = Bidi::reorderLine($text, $level);
            }
        }

        return $lines;
    }

    /**
     * The annotations of the ruby bases on a row of their own above the line of their bases, or below it, each over
     * its base; the markers of the bases taken out.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function placeRubies(array $lines): array
    {
        $placed = [];
        // The bases opened at the very end of a line, a long word broken there: they start the next line
        $carried = [];
        foreach ($lines as $line) {
            if (!preg_match_all('/\x1b\]'.self::CLONE_CODE.';([rR])(\d+)\x07/', $line, $markers, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE)) {
                $markers = [];
                if ([] === $carried) {
                    $placed[] = $line;
                    continue;
                }
            }
            /** @var list<array{int, string}> $over */
            $over = [];
            /** @var list<array{int, string}> $under */
            $under = [];
            /** @var array<int, int> $open */
            $open = array_fill_keys($carried, 0);
            $carried = [];
            $close = function (int $id, int $column) use (&$open, &$over, &$under): void {
                [$cells, $below] = $this->rubies[$id];
                $start = $open[$id];
                unset($open[$id]);
                // A base cut at the end of the line: its annotation as far as it goes
                $cells = $column - $start < AnsiUtils::visibleWidth($cells) ? AnsiUtils::truncateToWidth($cells, max(0, $column - $start), '') : $cells;
                if ($below) {
                    $under[] = [$start, $cells];
                } else {
                    $over[] = [$start, $cells];
                }
            };
            // The columns of the markers, measured from one to the next: a line of emphasis has one per character
            $column = 0;
            $from = 0;
            foreach ($markers as [[$marker, $offset], [$kind], [$id]]) {
                $column += AnsiUtils::visibleWidth(substr($line, $from, $offset - $from));
                $from = $offset + \strlen($marker);
                if ('r' === $kind && isset($this->rubies[(int) $id])) {
                    $open[(int) $id] = $column;
                } elseif ('R' === $kind && isset($open[(int) $id])) {
                    $close((int) $id, $column);
                }
            }
            // The end of a base that ends the line goes with what follows it, on the next line; one that starts there, too
            $width = AnsiUtils::visibleWidth($line);
            foreach ($open as $id => $start) {
                if ($start === $width && $start > 0) {
                    unset($open[$id]);
                    $carried[] = $id;
                } else {
                    $close($id, $width);
                }
            }
            $line = preg_replace('/\x1b\]'.self::CLONE_CODE.';[rR]\d+\x07/', '', $line) ?? $line;
            $base = \count($placed) + ([] !== $over ? 1 : 0);
            if ([] !== $over) {
                $row = $this->besideCap(self::annotationRow($over), \count($placed));
                $this->annotationRows[\count($placed)] = ['base' => $base, 'row' => $row, 'segments' => $over];
                $placed[] = $row;
            }
            $placed[] = $line;
            if ([] !== $under) {
                $row = $this->besideCap(self::annotationRow($under), \count($placed));
                $this->annotationRows[\count($placed)] = ['base' => $base, 'row' => $row, 'segments' => $under];
                $placed[] = $row;
            }
        }

        return $placed;
    }

    /**
     * An annotation row on a row of a drop cap: the row of the letter in its first columns.
     */
    private function besideCap(string $row, int $at): string
    {
        if (null === $this->capRows || !isset($this->capRows[0][$at])) {
            return $row;
        }
        [$cap, $width] = $this->capRows;

        return $cap[$at].AnsiUtils::sliceToWidth($row, $width, max(0, AnsiUtils::visibleWidth($row) - $width));
    }

    /**
     * @param list<array{int, string}> $segments The column of each annotation and its cells
     */
    private static function annotationRow(array $segments): string
    {
        $row = '';
        $column = 0;
        foreach ($segments as [$start, $cells]) {
            if ($start < $column) {
                continue;
            }
            $row .= str_repeat(' ', $start - $column).$cells;
            $column = $start + AnsiUtils::visibleWidth($cells);
        }

        return $row;
    }

    /**
     * The insets of the lines with the decorations of the boxes cloned on them: those of the boxes open before the line
     * filling its left, those still open after it following it.
     *
     * @param list<string>                                  $lines
     * @param list<array{int, int, 2?: string, 3?: string}> $base
     * @param int                                           $columns The width of the lines
     *
     * @return list<array{int, int, 2?: string, 3?: string}>
     */
    private function cloneInsets(array $lines, array $base, int $columns): array
    {
        $insets = [];
        $open = [];
        $last = \count($base) - 1;
        foreach ($lines as $i => $line) {
            [$left, $right] = $base[min($i, $last)] ?? [0, 0];
            $fill = $base[min($i, $last)][2] ?? '';
            $suffix = '';
            foreach ($open as $id => $_) {
                $fill .= $this->clones[$id][0];
            }
            preg_match_all('/\x1b\]'.self::CLONE_CODE.';([oc])(\d+)\x07/', $line, $markers, \PREG_SET_ORDER);
            foreach ($markers as [, $kind, $id]) {
                if ('o' === $kind) {
                    $open[(int) $id] = true;
                } else {
                    unset($open[(int) $id]);
                }
            }
            foreach (array_reverse(array_keys($open)) as $id) {
                $suffix .= $this->clones[$id][1];
            }
            // Never wider than the line, a column of text left: the start of what is on the left, from its border, and the
            // end of what is on the right, to its border
            $room = max(0, $columns - 1 - $left - $right);
            if (AnsiUtils::visibleWidth($fill) + AnsiUtils::visibleWidth($suffix) > $room) {
                $fill = AnsiUtils::truncateToWidth($fill, $fillRoom = min(AnsiUtils::visibleWidth($fill), intdiv($room + 1, 2)), '');
                $suffixWidth = AnsiUtils::visibleWidth($suffix);
                $suffix = AnsiUtils::sliceToWidth($suffix, max(0, $suffixWidth - ($room - $fillRoom)), min($suffixWidth, $room - $fillRoom));
            }
            $fillWidth = AnsiUtils::visibleWidth($fill) - AnsiUtils::visibleWidth($base[min($i, $last)][2] ?? '');
            $insets[] = [$left + $fillWidth, $right + AnsiUtils::visibleWidth($suffix), $fill, ($base[min($i, $last)][3] ?? '').$suffix];
        }
        $insets[] = $base[$last] ?? [0, 0];

        return $insets;
    }

    /**
     * @return list<string>
     */
    private function renderBlock(RenderContext $context): array
    {
        if (null === $this->dropCap || null === $this->block->initialLetter || null === $this->block->firstLetter) {
            return $this->renderHung($context);
        }

        // initial-letter: the letter as tall as its size, the first lines of the text beside it
        [$size, $sink] = $this->block->initialLetter;
        $style = $this->block->firstLetter;
        $registry = $context->getFontRegistry();
        $font = (null !== $style->getFont() ? $registry->first($style->getFont()) : null) ?? match (true) {
            $size >= 6 => 'big',
            $size >= 5 => 'standard',
            $size >= 4 => 'small',
            $size >= 3 => 'mini',
            default => null,
        };
        // The punctuation before the letter, which fonts may not have, at the top of it as it is
        preg_match('/^(\p{P}*)(.*)$/su', $this->dropCap, $parts);
        $glyph = null !== $font && $registry->has($font) && '' !== $parts[2] ? (new FigletRenderer($registry->get($font)))->render($parts[2]) : [$parts[2]];
        // The rows of the glyph drawn, its blank ones at the top dropped
        while (\count($glyph) > 1 && '' === trim($glyph[0])) {
            array_shift($glyph);
        }
        if ('' !== $parts[1]) {
            $indent = str_repeat(' ', AnsiUtils::visibleWidth($parts[1]));
            $glyph = array_map(static fn (string $row, int $i) => (0 === $i ? $parts[1] : $indent).$row, $glyph, array_keys($glyph));
        }
        $width = max(array_map(AnsiUtils::visibleWidth(...), $glyph)) + 1;
        if ($width >= $context->getColumns()) {
            return $this->renderHung($context);
        }
        $rows = max(\count($glyph), min($sink, 100));
        $cap = [];
        for ($i = 0; $i < $rows; ++$i) {
            $row = $glyph[$i] ?? '';
            $cap[] = $style->apply($row)."\x1b[0m".str_repeat(' ', $width - AnsiUtils::visibleWidth($row));
        }

        $base = $this->getLineInsets() ?: [[0, 0]];
        $last = \count($base) - 1;
        $insets = [];
        for ($i = 0; $i < max($rows, $last) + 1; ++$i) {
            $inset = $base[min($i, $last)];
            $insets[] = $i < $rows ? [$inset[0] + $width, $inset[1], $cap[$i].($inset[2] ?? '')] + $inset : $inset;
        }
        try {
            for ($pass = 0; $pass < 3; ++$pass) {
                $this->setLineInsets($insets);
                $lines = $this->renderHung($context);
                if ([] === $this->rubies) {
                    break;
                }
                // The rows of the letter are those drawn: the annotations of ruby over or under the lines take some
                [$rowOf] = $this->annotatedRows($lines);
                $next = [];
                foreach ($rowOf as $i => $row) {
                    $inset = $base[min($i, $last)];
                    $next[] = $row < $rows ? [$inset[0] + $width, $inset[1], $cap[$row].($inset[2] ?? '')] + $inset : $inset;
                }
                $next[] = $base[$last];
                if ($next === $insets) {
                    break;
                }
                $insets = $next;
            }
        } finally {
            $this->setLineInsets($base === [[0, 0]] ? [] : $base);
        }
        // The rows of the letter beside the annotations, drawn once they are placed (see placeRubies())
        $this->capRows = [] !== $this->rubies ? [$cap, $width] : null;
        // A text shorter than the letter: its rows below it
        for ($i = [] !== $this->rubies ? $this->annotatedRows($lines)[1] : \count($lines); $i < $rows; ++$i) {
            $lines[] = rtrim($cap[$i]);
        }

        return $lines;
    }

    /**
     * The row each line is drawn on, below the annotations of ruby over it and those under the lines before, and the
     * rows of all of them.
     *
     * @param list<string> $lines
     *
     * @return array{list<int>, int}
     */
    private function annotatedRows(array $lines): array
    {
        $rowOf = [];
        $row = 0;
        foreach ($lines as $line) {
            $over = false;
            $under = false;
            if (preg_match_all('/\x1b\]'.self::CLONE_CODE.';r(\d+)\x07/', $line, $markers)) {
                foreach ($markers[1] as $id) {
                    if ($this->rubies[(int) $id][1] ?? false) {
                        $under = true;
                    } else {
                        $over = true;
                    }
                }
            }
            $row += $over ? 1 : 0;
            $rowOf[] = $row;
            $row += $under ? 2 : 1;
        }

        return [$rowOf, $row];
    }

    /**
     * @return list<string>
     */
    private function renderHung(RenderContext $context): array
    {
        [$left, $right] = $this->block->hang;
        if (0 === $left && 0 === $right) {
            return $this->renderMarked($context);
        }

        // hanging-punctuation: the padding the punctuation can go over is in the columns, kept clear of the text
        $base = $this->getLineInsets() ?: [[0, 0]];
        $plain = trim(AnsiUtils::stripAnsiCodes($this->getText()));
        $shift = static fn (array $inset, int $l, int $r): array => [$inset[0] + $l, $inset[1] + $r] + $inset;
        $insets = array_map(static fn (array $inset) => $shift($inset, $left, $right), $base);
        // first: an opening bracket or quote starting the text starts in the padding
        if ($left > 0 && preg_match('/^[\p{Ps}\p{Pi}"\'«]/u', $plain, $m) && ($width = AnsiUtils::visibleWidth($m[0])) <= $left) {
            array_unshift($insets, $shift($base[0], $left - $width, $right));
        }
        try {
            $this->setLineInsets($insets);
            $lines = $this->renderMarked($context);
            // allow-end: a stop or a comma ending a line goes over the padding, the line taking it there, but not another
            // character: the lines that would are wrapped narrower again
            if ($right > 0 && $this->block->hangEnd) {
                $free = array_map(static fn (array $inset) => $shift($inset, 0, -$right), $insets);
                $last = \count($free) - 1;
                for ($pass = 0; $pass < 6; ++$pass) {
                    $this->setLineInsets($free);
                    $hung = $this->renderMarked($context);
                    $wrong = false;
                    foreach ($hung as $i => $line) {
                        $inset = $free[min($i, $last)];
                        $text = rtrim(AnsiUtils::stripAnsiCodes($line));
                        $room = $context->getColumns() - $inset[1] - $right;
                        // Over the padding: only its last character, and only a stop or a comma
                        if (AnsiUtils::visibleWidth($text) > $room && (!preg_match('/[,.\x{060C}\x{06D4}\x{3001}\x{3002}\x{FF0C}\x{FF0E}\x{FE50}-\x{FE52}\x{FF61}\x{FF64}]$/u', $text, $stop) || AnsiUtils::visibleWidth($text) - AnsiUtils::visibleWidth($stop[0]) > $room)) {
                            // Kept clear of the padding, this line and those after it wrapped again
                            for ($j = \count($free); $j <= $i + 1; ++$j) {
                                $free[] = $free[$last];
                            }
                            $free[$i] = $shift($free[$i], 0, $right);
                            $last = \count($free) - 1;
                            $wrong = true;
                            break;
                        }
                    }
                    if (!$wrong) {
                        $insets = $free;
                        $lines = $hung;
                        break;
                    }
                }
                $this->setLineInsets($insets);
            }
            // last: a closing bracket or quote ending the text goes over the padding, the line before taking the last
            // word when that leaves one line less
            if ($right > 0 && ($count = \count($lines)) > 1 && preg_match('/[\p{Pe}\p{Pf}"\'»]$/u', $plain, $m) && ($width = AnsiUtils::visibleWidth($m[0])) <= $right) {
                $wider = $insets;
                $last = \count($wider) - 1;
                for ($i = \count($wider); $i <= $count; ++$i) {
                    $wider[] = $wider[$last];
                }
                $wider[$count - 2] = $shift($wider[$count - 2], 0, -$width);
                $this->setLineInsets($wider);
                $hung = $this->renderMarked($context);
                $end = AnsiUtils::stripAnsiCodes(rtrim(end($hung) ?: ''));
                if (\count($hung) === $count - 1 && str_ends_with(rtrim($end), $m[0])) {
                    $lines = $hung;
                }
            }
        } finally {
            $this->setLineInsets($base === [[0, 0]] ? [] : $base);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderMarked(RenderContext $context): array
    {
        // A marker outside the text: before the first line, the others aligned with the text after it
        if (null !== $marker = $this->block->marker) {
            $hang = min(AnsiUtils::visibleWidth($marker), max(0, $context->getColumns() - 1));
            $lines = $this->renderIndented($context->withColumns($context->getColumns() - $hang)) ?: [''];
            // Right to left, drawn as stored without the bidirectional algorithm: the marker at the end of the lines
            $stored = $this->block->rtl && !Bidi::isAvailable();
            $insets = $this->block->rtl ? [] : $this->getLineInsets();
            foreach ($lines as $i => $line) {
                $hung = str_repeat(' ', $hang);
                if (0 === $i) {
                    // Right to left, the marker is at the start of the line, on its right: its characters the other
                    // way, the text being drawn as stored
                    $hung = AnsiUtils::truncateToWidth($marker, $hang, '');
                    $hung = $stored ? implode('', array_reverse(grapheme_str_split($hung) ?: [])) : $hung;
                    $hung = $this->block->markerStyle?->apply($hung) ?? $hung;
                    // Isolated from the text, like the unicode-bidi: isolate of ::marker: a Hebrew one keeps its period after it
                    if (!$stored && Bidi::isAvailable()) {
                        $hung = ($this->block->rtl ? "\u{2067}" : "\u{2066}").$hung."\u{2069}";
                    }
                    if (null !== $image = $this->block->markerImage) {
                        $hung = self::markerImage(\array_slice($image, 0, max(0, $hang - 1)), $this->block->rtl, $hang);
                        // Its cells left to right whatever the direction of the text
                        if (!$stored && Bidi::isAvailable()) {
                            $hung = "\u{2066}".$hung."\u{2069}";
                        }
                    }
                }
                // Before the resets that end the line: in the style of the line, like the marker before it
                if ($stored) {
                    $lines[$i] = preg_replace('/(?:\e\[[0-9;:]*m)*$/', addcslashes($hung, '\\$').'$0', $line, 1) ?? $line;
                } elseif (0 < $left = $insets[min($i, \count($insets) - 1)][0] ?? 0) {
                    // Beside a float at its start, like an image floated at the start of a list item: after it, at the
                    // start of the line, not under it
                    [$before, $after] = AnsiUtils::splitAround($line, $left, 0);
                    $lines[$i] = $before.$hung.$after;
                } else {
                    $lines[$i] = $hung.$line;
                }
            }

            return $lines;
        }

        return $this->renderIndented($context);
    }

    /**
     * An image drawn as a marker, in half blocks: the colors of the two pixels of each cell, then a space, before it
     * on the right of a line running right to left.
     *
     * @param list<array{?array{int, int, int}, ?array{int, int, int}}> $cells
     */
    private static function markerImage(array $cells, bool $rtl, int $width): string
    {
        $drawn = '';
        foreach ($cells as [$top, $bottom]) {
            $drawn .= match (true) {
                null === $top && null === $bottom => ' ',
                null === $bottom => "\e[38;2;".implode(';', $top).'m▀',
                null === $top => "\e[38;2;".implode(';', $bottom).'m▄',
                default => "\e[38;2;".implode(';', $top).';48;2;'.implode(';', $bottom).'m▀',
            }."\e[39;49m";
        }
        $room = str_repeat(' ', max(0, $width - \count($cells)));

        return $rtl ? $room.$drawn : $drawn.$room;
    }

    /**
     * The lines but the first further in, like a negative or hanging text-indent: they wrap that much narrower.
     *
     * @return list<string>
     */
    private function renderIndented(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $pull = $this->block->indents($columns)[1];
        // Pulled out further than the block is wide, like the text-indent: -200px of a link replaced by a logo, its
        // first line starts out of sight: laid out as wide as that, it shows past the pull, if anything; the lines
        // after it would have no room: text of more lines keeps them in the block
        if ($pull >= $columns && !$this->block->hanging) {
            $shown = static fn (array $lines): int => \count(array_filter($lines, static fn (string $line): bool => '' !== trim(AnsiUtils::stripAnsiCodes($line))));
            $lines = $this->renderText($context);
            // A line no wider than the block is all out of sight, like that of text-indent: -9999px: not laid out as wide
            if (1 === $shown($lines)) {
                return array_map(static fn (string $line): string => '', $lines);
            }
            $wide = $this->renderText($context->withColumns($columns + $pull));
            if (1 !== $shown($wide)) {
                return $lines;
            }
            foreach ($wide as $i => $line) {
                if ('' !== $line) {
                    // Pulled out past the start of the line: the left of a line from left to right, the right of one from
                    // right to left
                    $wide[$i] = rtrim(AnsiUtils::sliceByColumn($line, $this->block->rtl ? 0 : $pull, $columns));
                }
            }

            return $wide;
        }
        $others = min($pull, max(0, $columns - 1));
        if ($others <= 0) {
            return $this->renderText($context);
        }
        $lines = $this->renderText($context->withColumns($columns - $others));
        $indent = str_repeat(' ', $others);
        foreach ($lines as $i => $line) {
            // The blank rows of line-height stay blank
            if ($i > 0 && '' !== $line) {
                $lines[$i] = $indent.$line;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderText(RenderContext $context): array
    {
        $lines = array_values(parent::render($context));
        $columns = $context->getColumns();

        // text-wrap: balance, the narrowest width that keeps as many lines
        if ($this->block->balance && \count($lines) > 1 && 'nowrap' !== $this->block->whiteSpace && !$this->truncateLines) {
            $count = \count($lines);
            $low = 1;
            $high = $columns;
            while ($low < $high) {
                $middle = intdiv($low + $high, 2);
                if (\count(parent::render($context->withColumns($middle))) <= $count) {
                    $high = $middle;
                } else {
                    $low = $middle + 1;
                }
            }
            $context = $context->withColumns($low);
            $lines = parent::render($context);
        }

        // text-wrap: pretty, no last line of a single word: a narrower width that brings a word down, keeping the lines
        if ($this->block->pretty && !$this->block->balance && \count($lines) > 1 && 'nowrap' !== $this->block->whiteSpace && !$this->truncateLines && self::singleWord(end($lines))) {
            $count = \count($lines);
            for ($width = $columns - 1, $least = max(1, intdiv($columns * 3, 4)); $width >= $least; --$width) {
                $narrower = parent::render($context->withColumns($width));
                if (\count($narrower) > $count) {
                    break;
                }
                if (!self::singleWord(end($narrower))) {
                    $context = $context->withColumns($width);
                    $lines = $narrower;
                    break;
                }
            }
        }

        // Like ::first-line: the text composed again, the columns of the first line with its style
        if (null !== $this->block->firstLine && [] !== $lines) {
            $this->firstLineColumns = AnsiUtils::visibleWidth(trim(AnsiUtils::stripAnsiCodes($lines[0])));
            try {
                $lines = parent::render($context);
            } finally {
                $this->firstLineColumns = -1;
            }
        }

        $lines = self::carryLinks(array_values($lines));

        // line-clamp: the lines past the limit are dropped, the last one shown ends with an ellipsis
        if ($this->block->lineClamp > 0 && \count($lines) > $this->block->lineClamp) {
            $lines = \array_slice($lines, 0, $this->block->lineClamp);
            $last = array_pop($lines);
            $lines[] = AnsiUtils::truncateToWidth(rtrim($last), max(0, $columns - 1), '').'…';
        }

        if (0 === $this->block->lineGap || \count($lines) < 2) {
            return $lines;
        }

        // Like line-height: blank rows between the lines, none after the last one
        $spaced = [];
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                array_push($spaced, ...array_fill(0, $this->block->lineGap, ''));
            }
            $spaced[] = $line;
        }

        return $spaced;
    }

    /**
     * Aligns each line within the widest one, like CSS text-align; the block is then aligned as a whole.
     *
     * Justified lines are stretched at their spaces, except the last line of each paragraph.
     *
     * @param list<string> $lines The lines rendered for these columns
     *
     * @return list<string>
     *
     * @internal
     */
    public function alignLines(array $lines, TextAlign $align, int $columns): array
    {
        // The rows of ruby annotations: the lines aligned without them, then each annotation over where its base went
        if ([] !== $rows = $this->annotationRows) {
            $bases = [];
            $map = [];
            foreach ($lines as $i => $line) {
                if (isset($rows[$i]) && $rows[$i]['row'] === $line) {
                    continue;
                }
                $map[$i] = \count($bases);
                $bases[] = $line;
            }
            $this->annotationRows = [];
            try {
                $aligned = $this->alignLines($bases, $align, $columns);
            } finally {
                $this->annotationRows = $rows;
            }
            foreach ($lines as $i => $line) {
                if (isset($map[$i])) {
                    $lines[$i] = $aligned[$map[$i]];
                } elseif (isset($map[$rows[$i]['base']]) && $bases[$map[$rows[$i]['base']]] !== $aligned[$map[$rows[$i]['base']]]) {
                    $shift = self::columnShift($bases[$map[$rows[$i]['base']]], $aligned[$map[$rows[$i]['base']]]);
                    $lines[$i] = $this->besideCap(self::annotationRow(array_map(static fn (array $segment) => [$shift($segment[0]), $segment[1]], $rows[$i]['segments'])), $i);
                }
            }

            return $lines;
        }

        $alignLast = $this->block->alignLast;
        // text-justify: none, the lines at the start instead
        $fill = false;
        if ('none' === $this->block->justify) {
            $start = $this->block->rtl ? TextAlign::Right : TextAlign::Left;
            $fill = TextAlign::Justify === $align || TextAlign::Justify === $alignLast;
            $align = TextAlign::Justify === $align ? $start : $align;
            $alignLast = TextAlign::Justify === $alignLast ? $start : $alignLast;
        }
        // Lines flowing around floats are as wide as the room beside them: not aligned in the whole width, which the
        // floats cover
        if ([] !== $this->getLineInsets()) {
            return $lines;
        }
        if (\count($lines) < 2 || (TextAlign::Left === $align && \in_array($alignLast, [null, TextAlign::Left], true)) || $this->truncateLines || 'nowrap' === $this->block->whiteSpace) {
            return $lines;
        }
        $widths = array_map(AnsiUtils::visibleWidth(...), $lines);
        // Justified lines fill the block, like CSS; the others are aligned within the widest, which keeps it as wide
        $widest = $fill || TextAlign::Justify === $align || TextAlign::Justify === $alignLast ? $columns : min($columns, max($widths));

        // The last line of each paragraph, between forced line breaks, is not justified, but as text-align-last says
        $last = [];
        if (TextAlign::Justify === $align || null !== $alignLast) {
            // Counted in rendered rows: line-height puts blank rows between the lines
            $index = 0;
            foreach (explode("\n", $this->getDisplayText(new RenderContext($columns, 1))) as $paragraph) {
                $index += \count(TextWrapper::wrapTextWithAnsi($paragraph, $columns, $this->block->hyphen) ?: ['']);
                $last[($index - 1) * (1 + $this->block->lineGap)] = true;
            }
        }

        foreach ($lines as $i => $line) {
            $free = $widest - $widths[$i];
            if ($free <= 0) {
                continue;
            }
            $lineAlign = isset($last[$i]) ? $alignLast ?? (TextAlign::Justify === $align ? TextAlign::Left : $align) : $align;
            $lines[$i] = match ($lineAlign) {
                TextAlign::Left => $line,
                TextAlign::Center => str_repeat(' ', $free >> 1).$line,
                TextAlign::Right => str_repeat(' ', $free).$line,
                TextAlign::Justify => self::justify($line, $free, $this->block->justify),
            };
        }

        return $lines;
    }

    /**
     * Where the columns of a line went once aligned: the spaces added before them and between its words counted.
     *
     * @return \Closure(int): int
     */
    private static function columnShift(string $from, string $to): \Closure
    {
        $before = grapheme_str_split(AnsiUtils::stripAnsiCodes($from)) ?: [];
        $after = grapheme_str_split(AnsiUtils::stripAnsiCodes($to)) ?: [];
        $columns = [];
        $column = 0;
        $moved = 0;
        $j = 0;
        foreach ($before as $grapheme) {
            while (isset($after[$j]) && $after[$j] !== $grapheme) {
                $moved += AnsiUtils::graphemeWidth($after[$j++]);
            }
            $columns[$column] = $moved;
            $column += AnsiUtils::graphemeWidth($grapheme);
            if (isset($after[$j])) {
                $moved += AnsiUtils::graphemeWidth($after[$j++]);
            }
        }
        $columns[$column] = $moved;

        return static fn (int $at): int => $columns[$at] ?? $at + $moved - $column;
    }

    /**
     * Spreads some columns over the spaces between the words of a line, the first gaps taking one more; with
     * inter-character, over the room between its characters, and so with auto on a line of wide characters without
     * spaces, like CJK text.
     *
     * @param 'auto'|'none'|'inter-word'|'inter-character' $mode
     */
    private static function justify(string $line, int $free, string $mode = 'auto'): string
    {
        $parts = preg_split('/(\x1b\[[0-9;:]*m|\x1b\]8;[^\x07]*\x07|\x1b_[^\x07]*\x07|\x1b\]\d+;[^\x07]*\x07)/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$line];
        // The spaces before the first word (an indent) are not gaps
        $text = ltrim(implode('', array_filter($parts, static fn (int $i) => 0 === $i % 2, \ARRAY_FILTER_USE_KEY)), ' ');
        $gaps = substr_count(rtrim($text, ' '), ' ');
        if ('inter-character' === $mode || ('auto' === $mode && 0 === $gaps && AnsiUtils::visibleWidth($text) > grapheme_strlen($text))) {
            return self::spreadCharacters($parts, rtrim($text, ' '), $free) ?? $line;
        }
        if (0 === $gaps) {
            return $line;
        }

        $each = intdiv($free, $gaps);
        $more = $free % $gaps;
        $gap = 0;
        $started = false;
        foreach ($parts as $i => $part) {
            if (1 === $i % 2) {
                continue;
            }
            $out = '';
            foreach (mb_str_split($part) as $char) {
                if (' ' === $char && $started && $gap < $gaps) {
                    $char .= str_repeat(' ', $each + ($gap < $more ? 1 : 0));
                    ++$gap;
                }
                $started = $started || ' ' !== $char;
                $out .= $char;
            }
            $parts[$i] = $out;
        }

        return implode('', $parts);
    }

    /**
     * Spreads some columns between the characters of a line, after its indent: null when it has one only.
     *
     * @param list<string> $parts The text of the line and, at odd indexes, its escape sequences
     */
    private static function spreadCharacters(array $parts, string $text, int $free): ?string
    {
        $gaps = (grapheme_strlen($text) ?: 1) - 1;
        if ($gaps < 1) {
            return null;
        }
        $each = intdiv($free, $gaps);
        $more = $free % $gaps;
        $gap = 0;
        $started = false;
        foreach ($parts as $i => $part) {
            if (1 === $i % 2 || '' === $part) {
                continue;
            }
            $out = '';
            foreach (grapheme_str_split($part) ?: [] as $grapheme) {
                // The room goes before each character after the first, so that none follows the last
                if ($started && $gap < $gaps) {
                    $out .= str_repeat(' ', $each + ($gap < $more ? 1 : 0));
                    ++$gap;
                }
                $started = $started || ' ' !== $grapheme;
                $out .= $grapheme;
            }
            $parts[$i] = $out;
        }

        return implode('', $parts);
    }

    /**
     * Whether its lines wrap at the width it gets, like CSS white-space: normal, pre-wrap and pre-line; with pre and
     * nowrap, a scroll container around it scrolls what is too wide sideways.
     */
    /**
     * Keeps the no-break spaces in the lines drawn, so that the words they join are measured as one.
     *
     * @internal
     */
    public function keepNoBreakSpaces(bool $keep): void
    {
        $this->keepNoBreakSpaces = $keep;
    }

    public function wrapsLines(): bool
    {
        return !\in_array($this->block->whiteSpace, ['pre', 'nowrap'], true);
    }

    /**
     * Returns the text without formatting, generated content included.
     */
    public function getPlainText(): string
    {
        $text = $this->getText();
        // A ruby: its annotation after its base, in parentheses, like without ruby; an emphasis mark, its base alone
        if (str_contains($text, "\x1b]".self::CLONE_CODE.';r')) {
            $text = preg_replace_callback('/\x1b\]'.self::CLONE_CODE.';r(\d+)\x07(.*?)\x1b\]'.self::CLONE_CODE.';R\1\x07/su', fn (array $m) => (preg_replace('/^(?:\x{00A0}\x{2060})+|(?:\x{2060}\x{00A0})+$/u', '', $m[2]) ?? $m[2]).(($this->rubies[(int) $m[1]][2] ?? false) ? '' : '('.trim(AnsiUtils::stripAnsiCodes($this->rubies[(int) $m[1]][0] ?? '')).')'), $text) ?? $text;
            $text = str_replace([self::WORD_JOINER, "\u{00A0}"], ['', ' '], $text);
        }

        // An image: its text alternative
        $text = $this->images()->plain($text);

        return preg_replace('/\x1b\[[0-9;]*m|\x1b\]8;[^\x07]*\x07|\x1b\]'.self::CLONE_CODE.';[oc]\d+\x07/', '', $text) ?? '';
    }

    private function refresh(): void
    {
        // Like CSS, a line that does not wrap is clipped unless text-overflow says otherwise
        $nowrap = 'nowrap' === $this->block->whiteSpace;
        $this->setTruncate($this->truncateLines || $nowrap, $this->block->ellipsis ?? ($nowrap ? '' : '...'));
        parent::setText($this->compose());
    }

    /**
     * Composes the text with the resolved style of the widget as the base of
     * every run: a run that ends resets its attributes to the terminal
     * defaults, which would otherwise drop the widget color for what follows.
     */
    protected function getDisplayText(RenderContext $context): string
    {
        $base = $context->getStyle();
        // An indent in percent depends on the width, and so does one as wide as it
        if ('' === $base->getAnsiRestore() && 0.0 === $this->block->indentPercent && $this->firstLineColumns < 0 && $this->block->indents($context->getColumns())[0] < $context->getColumns()) {
            return parent::getDisplayText($context);
        }

        return $this->compose('' === $base->getAnsiRestore() ? null : $base, $context->getColumns());
    }

    protected function getHyphen(): string
    {
        return $this->block->hyphen;
    }

    /**
     * The text composed over a base style, like it is drawn in a widget of that style: what a run resets goes back to
     * the base.
     *
     * @internal
     */
    public function getTextOver(Style $base): string
    {
        return '' === $base->getAnsiRestore() ? $this->getText() : $this->compose($base);
    }

    /** The most ranges of the custom highlights of a text block */
    /** @var \SplObjectStorage<InlineNode, list<array{string, list<Style>}>>|null The highlighted pieces of the text nodes, see highlightPieces() */
    private ?\SplObjectStorage $highlighted = null;

    private const MAX_HIGHLIGHTS = 2_000;

    /**
     * The pieces of the text nodes the custom highlights of the block cover, each with the styles of those over it:
     * their texts are found in the text of its nodes joined, across its inline elements, like ranges.
     *
     * @return \SplObjectStorage<InlineNode, list<array{string, list<Style>}>>|null
     */
    private function highlightPieces(): ?\SplObjectStorage
    {
        if (!$this->block->highlights) {
            return null;
        }
        [$source, $texts] = $this->source();

        $ranges = [];
        foreach ($this->block->highlights as $highlight) {
            [$pattern, $style] = $highlight;
            if (!preg_match_all($pattern, $source, $matches, \PREG_OFFSET_CAPTURE)) {
                continue;
            }
            // The first group when there is one, like the text between the prefix and the suffix of a text fragment
            foreach (\array_slice($matches[1] ?? $matches[0], 0, $highlight[2] ?? null) as [$match, $offset]) {
                // At most that many, but the text a text fragment goes to, which is always drawn
                if ('' !== $match && $offset >= 0 && (\count($ranges) < self::MAX_HIGHLIGHTS || isset($highlight[2]))) {
                    $ranges[] = [$offset, $offset + \strlen($match), $style];
                }
            }
        }
        /** @var \SplObjectStorage<InlineNode, list<array{string, list<Style>}>> $pieces */
        $pieces = new \SplObjectStorage();
        foreach ($texts as [$node, $start]) {
            $length = \strlen($node->text);
            $over = array_filter($ranges, static fn (array $range) => $range[0] < $start + $length && $range[1] > $start);
            if (!$over) {
                continue;
            }
            $cuts = [0, $length];
            foreach ($over as [$from, $to]) {
                $cuts[] = max(0, $from - $start);
                $cuts[] = min($length, $to - $start);
            }
            $cuts = array_values(array_unique($cuts));
            sort($cuts);
            $list = [];
            for ($i = 1, $count = \count($cuts); $i < $count; ++$i) {
                [$from, $to] = [$cuts[$i - 1], $cuts[$i]];
                $marks = array_values(array_map(static fn (array $range) => $range[2], array_filter($over, static fn (array $range) => $range[0] - $start <= $from && $range[1] - $start >= $to)));
                $list[] = [substr($node->text, $from, $to - $from), $marks];
            }
            $pieces[$node] = $list;
        }

        return $pieces;
    }

    /**
     * The text of the text nodes joined, a line break for each <br>, which highlights and text fragments are found in.
     *
     * @internal
     */
    public function getSourceText(): string
    {
        return $this->source()[0];
    }

    /**
     * @return array{string, list<array{InlineNode, int}>} The text, and each text node with where it starts in it
     */
    private function source(): array
    {
        $source = '';
        $texts = [];
        $collect = static function (array $nodes) use (&$collect, &$source, &$texts): void {
            foreach ($nodes as $node) {
                if ($node->isText()) {
                    $texts[] = [$node, \strlen($source)];
                    $source .= $node->text;
                } elseif ('br' === $node->tagName) {
                    $source .= "\n";
                } else {
                    $collect($node->children);
                }
            }
        };
        $collect($this->nodes);

        return [$source, $texts];
    }

    /**
     * The text with its first word, up to the first space or line break, left out of sight on a first line of its own:
     * its escape sequences kept, which style and link what follows.
     */
    private static function withoutFirstWord(string $text): string
    {
        $out = '';
        $word = false;
        foreach (preg_split('/(\e\[[0-9;:?]*[A-Za-z]|\e\][^\x07\e]*(?:\x07|\e\\\\)|\e_[^\x07]*\x07)/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_OFFSET_CAPTURE) ?: [] as [$part, $offset]) {
            if ("\e" === $part[0]) {
                $out .= $part;
                continue;
            }
            // The spaces before the word are left out with it, the line break or the spaces after it end it
            $at = $word ? 0 : \strlen($part) - \strlen(ltrim($part, " \t"));
            // A line break before any word: the first line holds nothing, the text is as it is
            if (!$word && "\n" === ($part[$at] ?? '')) {
                return $text;
            }
            if (!preg_match('/[ \t\n]/', $part, $m, \PREG_OFFSET_CAPTURE, $at)) {
                $word = $word || $at < \strlen($part);
                continue;
            }
            // The spaces after it, and the line break ending its line, which the one put before the rest ends
            $rest = ltrim(substr($text, $offset + $m[0][1]), " \t");

            return $out."\n".(str_starts_with($rest, "\n") ? substr($rest, 1) : $rest);
        }

        return $out;
    }

    private function compose(?Style $base = null, int $columns = 0): string
    {
        $this->clones = [];
        $this->rubies = [];
        $this->hyphenator = $this->block->hyphenator;
        $this->emphasis = null !== $this->block->emphasis ? $this->block : null;
        $this->dropCap = null;
        $this->highlighted = $this->highlightPieces();
        $state = ['text' => '', 'index' => 0, 'pendingSpace' => false, 'firstLetter' => false, 'spaced' => false, 'link' => 0, 'firstLine' => max(0, $this->firstLineColumns), 'firstLineAt' => null !== $base ? 1 : 0];
        $block = $this->block;
        $styles = null !== $base ? [$base] : [];
        // The whole block is the link with the focus
        if (0 === $this->currentLink && null !== $this->blockHref && !self::linksOf($this->nodes)) {
            $styles[] = new Style(reverse: true);
            $state['text'] .= AnsiUtils::cursorMarker();
        }

        if (null !== $block->before) {
            $this->emit($state, $block->before, null !== $block->beforeStyle ? [...$styles, $block->beforeStyle] : $styles, $block->textTransform, 'pre');
        }
        $this->walk($this->nodes, $state, $styles, $block->textTransform, $block->whiteSpace, $block->letterSpacing, $block->script, wordSpacing: $block->wordSpacing);
        if (null !== $block->after) {
            $this->emit($state, $block->after, null !== $block->afterStyle ? [...$styles, $block->afterStyle] : $styles, $block->textTransform, 'pre');
        }

        [$first] = $block->indents($columns);
        // Pushed in as far as the block is wide, like the text-indent: 9999px of a link replaced by a logo: the first line
        // holds its first word out of sight anyway, the others the rest
        if ($columns > 0 && $first >= $columns && '' !== $state['text'] && !\in_array($block->whiteSpace, ['nowrap', 'pre'], true) && !$block->eachLine) {
            $state['text'] = self::withoutFirstWord($state['text']);
            $first = 0;
        }
        if ($first > 0 && '' !== $state['text']) {
            // Like text-indent: the first line starts further in, ::before and markers included, and with each-line
            // the lines after a forced break
            $indent = str_repeat(' ', $first);
            $state['text'] = $indent.($block->eachLine ? str_replace("\n", "\n".$indent, $state['text']) : $state['text']);
        }

        if (null !== $this->link && '' !== $state['text']) {
            return "\x1b]8;;".$this->link."\x07".$state['text']."\x1b]8;;\x07";
        }

        return $state['text'];
    }

    /**
     * @param list<InlineNode>                                                                                                                                                                                             $nodes
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     * @param list<Style>                                                                                                                                                                                                  $styles
     */
    private function walk(array $nodes, array &$state, array $styles, ?string $transform, string $whiteSpace, int $letterSpacing = 0, ?string $script = null, bool $reversed = false, int $wordSpacing = 0): void
    {
        // Right to left (<bdo dir="rtl">): the nodes backwards, each element with the index of its format
        $starts = [];
        $index = $state['index'];
        foreach ($nodes as $i => $node) {
            $starts[$i] = $index;
            $index += $node->isText() ? 0 : 1 + self::countElements($node->children);
        }
        $order = $reversed ? array_reverse(array_keys($nodes)) : array_keys($nodes);

        foreach ($order as $i) {
            $node = $nodes[$i];
            $state['index'] = $starts[$i];
            if ($node->isText()) {
                $at = \strlen($state['text']);
                if (!$reversed && isset($this->highlighted[$node])) {
                    // Its highlighted pieces in the styles of their highlights, over its own
                    foreach ($this->highlighted[$node] as [$piece, $marks]) {
                        // A space left at the end of a piece is in the highlights of that piece
                        if ($marks !== ($state['pendingMarks'] ?? []) && $state['pendingSpace'] && '' !== $state['text']) {
                            $state['pendingSpace'] = false;
                            $this->emit($state, ' ', [...$styles, ...$state['pendingMarks'] ?? []], null, 'pre', words: true, wordSpacing: $wordSpacing);
                            $state['spaced'] = true;
                        }
                        $this->emit($state, $piece, [...$styles, ...$marks], $transform, $whiteSpace, $letterSpacing, $script, true, $wordSpacing);
                        $state['pendingMarks'] = $marks;
                    }
                } else {
                    $this->emit($state, $reversed ? implode('', array_reverse(grapheme_str_split($node->text) ?: [])) : $node->text, $styles, $transform, $whiteSpace, $letterSpacing, $script, true, $wordSpacing);
                    $state['pendingMarks'] = [];
                }
                if (null !== $this->emphasis && !$this->truncateLines) {
                    $this->emphasize($state, $at, $styles);
                }
                continue;
            }

            $format = $this->formats[$state['index']++] ?? null;
            if ($format?->hidden) {
                $state['index'] += self::countElements($node->children);
                continue;
            }

            if ('br' === $node->tagName) {
                $state['text'] .= "\n";
                $state['pendingSpace'] = false;
                $state['spaced'] = false;
                continue;
            }
            // A line break opportunity: a zero-width space, where the line can wrap
            if ('wbr' === $node->tagName) {
                $state['text'] .= "\u{200B}";
                continue;
            }

            $nodeStyles = null !== $format?->style ? [...$styles, $format->style] : $styles;
            // The link with the focus: reversed, the cursor at its start, which screen readers follow
            $focusedLink = false;
            if ('a' === $node->tagName && '' !== trim($node->attributes['href'] ?? '')) {
                $focusedLink = $state['link']++ === $this->currentLink;
                // Reversed, unless a background is its look, like a:focus { background: yellow }
                if ($focusedLink && null === $format?->style?->getBackground()) {
                    $nodeStyles[] = new Style(reverse: true);
                }
            }
            $nodeTransform = null !== $format ? $format->textTransform : $transform;
            $nodeWhiteSpace = $format->whiteSpace ?? $whiteSpace;
            // In an inline block, one box in the line: its words kept together, wrapped only when too wide for a line
            $atomic = true === $format?->atomic;
            if (($atomic || ($state['atomic'] ?? 0) > 0) && 'normal' === $nodeWhiteSpace) {
                $nodeWhiteSpace = 'nowrap';
            }

            // The space before an element is outside it, in the styles around it: not underlined, not linked
            if ($state['pendingSpace'] && '' !== $state['text']) {
                $state['pendingSpace'] = false;
                $this->emit($state, ' ', [...$styles, ...$state['pendingMarks'] ?? []], null, 'pre', words: true, wordSpacing: $wordSpacing);
                // Whitespace at the start of the element collapses into it
                $state['spaced'] = true;
            }
            $href = 'a' === $node->tagName ? self::linkTarget($this->resolve($node->attributes['href'] ?? '')) : null;
            $fragment = 'a' === $node->tagName && null === $href ? self::fragmentTarget($node->attributes['href'] ?? '') : null;
            // An id, which terminals group the pieces of a link by, and which says where each link is in the frame
            $linkId = null !== $href || null !== $fragment ? 'id='.self::linkId($this, $state['link'] - 1) : '';
            $marked = '' !== $linkId || 'a' !== $node->tagName || '' === trim($node->attributes['href'] ?? '') ? false : true;
            if (null !== $href) {
                $state['text'] .= "\x1b]8;".$linkId.';'.$href."\x07";
            } elseif (null !== $fragment) {
                $state['text'] .= "\x1b]8;".self::FRAGMENT_LINK.':'.$linkId.';'.$fragment."\x07";
            } elseif ($marked) {
                $state['text'] .= "\x1b]8;".self::LINK_MARK.':id='.self::linkId($this, $state['link'] - 1).";-\x07";
            }
            if ($focusedLink) {
                $this->emitRaw($state, AnsiUtils::cursorMarker());
            }

            // The box of an inline element: its margin outside, its border, then its padding in its own colors
            [$marginLeft, $paddingLeft, $paddingRight, $marginRight] = $format->box ?? [0, 0, 0, 0];
            // box-decoration-break: clone, its padding and border on each of its lines: where it starts and ends is marked
            $clone = null;
            if (true === $format?->cloneDecoration && ($paddingLeft > 0 || $paddingRight > 0 || null !== $format->borderLeft || null !== $format->borderRight)) {
                $padding = Style::mergeAll($nodeStyles);
                $clone = \count($this->clones);
                $this->clones[] = [
                    ($format->borderLeft ?? '').($paddingLeft > 0 ? $padding->apply(str_repeat(' ', $paddingLeft)) : ''),
                    ($paddingRight > 0 ? $padding->apply(str_repeat(' ', $paddingRight)) : '').($format->borderRight ?? ''),
                ];
            }
            // Its margin and padding are no line breaks, like CSS: the text breaks at its spaces only, like before the
            // inline code of a paragraph, not between it and the comma after it; an inline block, one box in the line,
            // can start a line, like the items of a list displayed inline-block without spaces between them
            if ($atomic && ($state['drawn'] ?? false) && !$state['pendingSpace'] && !preg_match('/[ \t\n](?:\e\[[0-9;:]*m)*$/', $state['text'])) {
                $state['text'] .= "\u{200B}";
            }
            $state['breakAfter'] = false;
            if ($marginLeft > 0) {
                $this->emit($state, str_repeat("\u{00A0}", $marginLeft), $styles, null, 'pre');
            }
            if (null !== $format?->borderLeft) {
                $this->emitRaw($state, $format->borderLeft);
            }
            if ($paddingLeft > 0) {
                $this->emit($state, str_repeat("\u{00A0}", $paddingLeft), $nodeStyles, null, 'pre');
            }
            if (null !== $clone) {
                $state['text'] .= \sprintf(self::CLONE_MARKER, 'o', $clone);
            }
            // unicode-bidi: the element set apart for the bidirectional algorithm, like the controls of its embedding,
            // override or isolate around it; a <bdi> without dir in the direction of its first strong character
            [$bidiOpen, $bidiClose] = null !== $format && Bidi::isAvailable() ? self::bidiControls($format, 'bdi' === $node->tagName && !isset($node->attributes['dir'])) : ['', ''];
            $state['text'] .= $bidiOpen;
            if (null !== $format?->before) {
                $this->emit($state, $format->before, null !== $format->beforeStyle ? [...$nodeStyles, $format->beforeStyle] : $nodeStyles, $nodeTransform, 'pre');
            }
            // vertical-align is not inherited, but the text of the children is in the raised box; without the
            // bidirectional algorithm, a <bdo dir="rtl"> has its characters the other way
            $nodeReversed = 'bdo' === $node->tagName && !Bidi::isAvailable() ? 'rtl' === strtolower($node->attributes['dir'] ?? '') : $reversed;
            // hyphens: auto, in the language of the element
            $outerHyphenator = $this->hyphenator;
            $outerEmphasis = $this->emphasis;
            if (null !== $format) {
                $this->hyphenator = $format->hyphenator;
                $this->emphasis = null !== $format->emphasis ? $format : null;
            }
            // The text alternative of embedded content, like the [alt] of an <img>, stands for a replaced element: no marks
            if (\in_array($node->tagName, ['img', 'svg', 'iframe', 'video', 'audio', 'canvas', 'object', 'embed'], true)) {
                $this->emphasis = null;
            }
            if ('ruby' === $format?->ruby) {
                // Its annotations take the row the emphasis marks would: none in a ruby
                $this->emphasis = null;
                $this->walkRuby($node->children, $state, $nodeStyles, $nodeTransform, $nodeWhiteSpace, $format->letterSpacing, $format->script ?? $script, $nodeReversed, $format->wordSpacing);
            } else {
                $state['atomic'] = ($state['atomic'] ?? 0) + ($atomic ? 1 : 0);
                $this->walk($node->children, $state, $nodeStyles, $nodeTransform, $nodeWhiteSpace, null !== $format ? $format->letterSpacing : $letterSpacing, (null !== $format ? $format->script : null) ?? $script, $nodeReversed, null !== $format ? $format->wordSpacing : $wordSpacing);
                $state['atomic'] -= $atomic ? 1 : 0;
            }
            $this->hyphenator = $outerHyphenator;
            $this->emphasis = $outerEmphasis;
            if (null !== $format?->after) {
                $this->emit($state, $format->after, null !== $format->afterStyle ? [...$nodeStyles, $format->afterStyle] : $nodeStyles, $nodeTransform, 'pre');
            }
            $state['text'] .= $bidiClose;
            if (null !== $clone) {
                $state['text'] .= \sprintf(self::CLONE_MARKER, 'c', $clone);
            }
            if ($paddingRight > 0) {
                $this->emit($state, str_repeat("\u{00A0}", $paddingRight), $nodeStyles, null, 'pre');
            }
            if (null !== $format?->borderRight) {
                $this->emitRaw($state, $format->borderRight);
            }
            if ($marginRight > 0) {
                $this->emit($state, str_repeat("\u{00A0}", $marginRight), $styles, null, 'pre');
            }
            if ($atomic) {
                $state['breakAfter'] = true;
            }

            if (null !== $href) {
                $state['text'] .= "\x1b]8;;\x07";
            } elseif (null !== $fragment) {
                $state['text'] .= "\x1b]8;".self::FRAGMENT_LINK.";\x07";
            } elseif ($marked) {
                $state['text'] .= "\x1b]8;".self::LINK_MARK.";\x07";
            }
        }
        $state['index'] = $index;
    }

    /**
     * The controls that open and close an inline element for the bidirectional algorithm, like unicode-bidi.
     *
     * @return array{string, string}
     */
    private static function bidiControls(InlineFormat $format, bool $auto): array
    {
        if ($auto && \in_array($format->unicodeBidi, ['isolate', 'normal'], true)) {
            return ["\u{2068}", "\u{2069}"];
        }

        return match ($format->unicodeBidi) {
            'embed' => [$format->rtl ? "\u{202B}" : "\u{202A}", "\u{202C}"],
            'isolate' => [$format->rtl ? "\u{2067}" : "\u{2066}", "\u{2069}"],
            'bidi-override' => [$format->rtl ? "\u{202E}" : "\u{202D}", "\u{202C}"],
            'isolate-override' => [$format->rtl ? "\u{2067}\u{202E}" : "\u{2066}\u{202D}", "\u{202C}\u{2069}"],
            'plaintext' => ["\u{2068}", "\u{2069}"],
            default => ['', ''],
        };
    }

    /**
     * The content of a ruby: its bases, each marked in the text with its annotation kept aside, drawn over it once the
     * lines are laid out, like display: ruby-text.
     *
     * Like the HTML ruby model, the bases (<rb>, or the text before the annotations) and the annotations that follow
     * them pair one to one, the annotations left over going with the last base; the white space between them is not
     * drawn.
     *
     * @param list<InlineNode>                                                                                                                                                                                             $nodes
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     * @param list<Style>                                                                                                                                                                                                  $styles
     */
    private function walkRuby(array $nodes, array &$state, array $styles, ?string $transform, string $whiteSpace, int $letterSpacing, ?string $script, bool $reversed, int $wordSpacing): void
    {
        $starts = [];
        $index = $state['index'];
        foreach ($nodes as $i => $node) {
            $starts[$i] = $index;
            $index += $node->isText() ? 0 : 1 + self::countElements($node->children);
        }

        // The segments: their bases, each a list of nodes, and the annotations after them
        $segments = [];
        $bases = [];
        $run = [];
        $annotations = [];
        foreach ($nodes as $i => $node) {
            $format = $node->isText() ? null : $this->formats[$starts[$i]] ?? null;
            if ($node->isText() ? '' === trim($node->text, " \t\n\r\f") : true === $format?->hidden) {
                continue;
            }
            if (\in_array($format?->ruby, ['ruby-text', 'ruby-text-container'], true)) {
                if ([] !== $run) {
                    $bases[] = $run;
                    $run = [];
                }
                $annotations[] = $i;
                continue;
            }
            if ([] !== $annotations) {
                $segments[] = [$bases, $annotations];
                $bases = [];
                $annotations = [];
            }
            if ('ruby-base' === $format?->ruby) {
                if ([] !== $run) {
                    $bases[] = $run;
                    $run = [];
                }
                $bases[] = [$i];
                continue;
            }
            $run[] = $i;
        }
        if ([] !== $run) {
            $bases[] = $run;
        }
        $segments[] = [$bases, $annotations];

        $part = function (array $ids) use (&$state, $nodes, $starts, $styles, $transform, $whiteSpace, $letterSpacing, $script, $reversed, $wordSpacing): string {
            $at = \strlen($state['text']);
            foreach ($ids as $id) {
                $state['index'] = $starts[$id];
                $this->walk([$nodes[$id]], $state, $styles, $transform, $whiteSpace, $letterSpacing, $script, $reversed, $wordSpacing);
            }
            $text = substr($state['text'], $at);
            $state['text'] = substr($state['text'], 0, $at);

            return $text;
        };

        foreach ($segments as [$bases, $annotations]) {
            foreach ($bases as $k => $base) {
                // The annotations of the base, those left over with the last one
                $own = \count($bases) - 1 === $k ? \array_slice($annotations, $k) : (isset($annotations[$k]) ? [$annotations[$k]] : []);
                // The space before the base is outside it, like before an element
                if ([] !== $own && $state['pendingSpace'] && '' !== $state['text'] && !$state['spaced']) {
                    $state['pendingSpace'] = false;
                    $this->emit($state, ' ', [], null, 'pre', words: true, wordSpacing: $wordSpacing);
                    $state['spaced'] = true;
                }
                $text = $part($base);
                if ([] === $own) {
                    $state['text'] .= $text;
                    continue;
                }

                // The annotations, composed apart: not on the first line, without the spaces around them
                $after = [$state['pendingSpace'], $state['spaced'], $state['firstLine']];
                $annotation = '';
                foreach ($own as $id) {
                    $state['pendingSpace'] = false;
                    $state['spaced'] = true;
                    $piece = $part([$id]);
                    $annotation .= ('' !== $annotation && '' !== $piece ? ' ' : '').$piece;
                }
                [$state['pendingSpace'], $state['spaced'], $state['firstLine']] = $after;

                $format = $this->formats[$starts[$own[0]]] ?? new InlineFormat();
                $this->putRuby($state, $text, $annotation, $format->rubyUnder, 'start' === $format->rubyAlign);
            }
        }
        $state['index'] = $index;
    }

    /**
     * Adds a ruby base marked with its annotation, the base as wide as the annotation, and the base kept on one line.
     *
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     */
    private function putRuby(array &$state, string $base, string $annotation, bool $under = false, bool $start = false, bool $mark = false): void
    {
        $baseWidth = AnsiUtils::visibleWidth($base);
        $width = AnsiUtils::visibleWidth($annotation);
        if (0 === $baseWidth || 0 === $width) {
            $state['text'] .= $base;

            return;
        }
        // A line that cannot have a row of its own above it: the annotation after its base, in parentheses
        if ($this->truncateLines) {
            $state['text'] .= $base.'('.$annotation.')';

            return;
        }

        $base = self::glue($base);
        if ($width > $baseWidth) {
            $left = $start ? 0 : ($width - $baseWidth) >> 1;
            $base = str_repeat("\u{00A0}".self::WORD_JOINER, $left).$base.str_repeat(self::WORD_JOINER."\u{00A0}", $width - $baseWidth - $left);
            $baseWidth = $width;
        }
        $left = $start ? 0 : ($baseWidth - $width) >> 1;
        $id = \count($this->rubies);
        $this->rubies[] = [str_repeat(' ', $left).$annotation.str_repeat(' ', $baseWidth - $width - $left), $under, $mark];
        $state['text'] .= \sprintf(self::CLONE_MARKER, 'r', $id).$base.\sprintf(self::CLONE_MARKER, 'R', $id);
        $state['spaced'] = false;
    }

    /**
     * Marks the characters of the text composed since an offset with the emphasis mark of their format, like
     * text-emphasis: each a ruby base, its mark the annotation; not the spaces, the punctuation nor the controls.
     *
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     * @param list<Style>                                                                                                                                                                                                  $styles
     */
    private function emphasize(array &$state, int $at, array $styles): void
    {
        $format = $this->emphasis;
        if (null === $format || null === $format->emphasis || \strlen($state['text']) === $at) {
            return;
        }
        $text = substr($state['text'], $at);
        $state['text'] = substr($state['text'], 0, $at);
        [$pendingSpace, $spaced] = [$state['pendingSpace'], $state['spaced']];
        $mark = ($format->emphasisStyle ?? new Style(color: Style::mergeAll($styles)->getColor()))->apply($format->emphasis);

        $codes = '';
        foreach (preg_split('/(\x1b\[[0-9;:]*[A-Za-z]|\x1b\][^\x07]*\x07)/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$text] as $k => $part) {
            if (1 === $k % 2) {
                $codes .= $part;
                continue;
            }
            foreach ('' === $part ? [] : (grapheme_str_split($part) ?: [$part]) as $grapheme) {
                if (preg_match('/^[\s\p{Z}\p{P}\p{C}]/u', $grapheme)) {
                    $state['text'] .= $codes.$grapheme;
                } else {
                    // The codes before the character go before its marker, which the annotation is placed by
                    $state['text'] .= $codes;
                    $this->putRuby($state, $grapheme, $mark, $format->emphasisUnder, mark: true);
                }
                $codes = '';
            }
        }
        $state['text'] .= $codes;
        [$state['pendingSpace'], $state['spaced']] = [$pendingSpace, $spaced];
    }

    /**
     * The characters of a text joined: no line breaks between them, its spaces no-break ones.
     */
    private static function glue(string $text): string
    {
        $glued = '';
        $joined = false;
        foreach (preg_split('/(\x1b\[[0-9;:]*[A-Za-z]|\x1b\][^\x07]*\x07)/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [$text] as $k => $part) {
            if (1 === $k % 2) {
                $glued .= $part;
                continue;
            }
            if ('' === $part) {
                continue;
            }
            $glued .= ($joined ? self::WORD_JOINER : '').implode(self::WORD_JOINER, grapheme_str_split(str_replace(' ', "\u{00A0}", $part)) ?: [$part]);
            $joined = true;
        }

        return $glued;
    }

    /**
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     * @param list<Style>                                                                                                                                                                                                  $styles
     */
    private function emit(array &$state, string $text, array $styles, ?string $transform, string $whiteSpace, int $letterSpacing = 0, ?string $script = null, bool $words = false, int $wordSpacing = 0): void
    {
        $text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
        if ('' === $text) {
            // Nothing to draw, like empty generated content: a pending space stays pending
            return;
        }

        if ('normal' === $whiteSpace || 'nowrap' === $whiteSpace) {
            // Collapse whitespace across runs, and drop it at the start and end of the block: the white space of CSS,
            // not the no-break spaces, which \s matches in UTF-8
            $collapsed = preg_replace('/[ \t\n\r\f]+/', ' ', $text);
            if ('' === trim($collapsed)) {
                $state['pendingSpace'] = $state['pendingSpace'] || ('' !== $state['text'] && !$state['spaced']);

                return;
            }
            $space = (str_starts_with($collapsed, ' ') || $state['pendingSpace']) && '' !== $state['text'] && !$state['spaced'];
            $state['pendingSpace'] = str_ends_with($collapsed, ' ');
            $text = trim($collapsed);
            // An inline element that does not wrap, like <nobr>, in a block that does: its words are not broken apart
            if ('nowrap' === $whiteSpace && 'nowrap' !== $this->block->whiteSpace) {
                $text = str_replace(' ', "\u{00A0}", $text);
            }
        } else {
            // Preserved whitespace, and generated content: a space collapsed before still separates
            $space = $state['pendingSpace'] && '' !== $state['text'];
            $state['pendingSpace'] = false;
            if ('pre-line' === $whiteSpace) {
                $text = preg_replace('/[ \t]+/', ' ', $text);
            }
        }

        foreach (null !== $transform ? explode('+', $transform) : [] as $keyword) {
            $text = match ($keyword) {
                'uppercase' => mb_strtoupper($text),
                'lowercase' => mb_strtolower($text),
                'capitalize' => preg_replace_callback('/(^|[\s\p{P}])(\p{Ll})/u', static fn (array $m) => $m[1].mb_strtoupper($m[2]), $text),
                // Like text-transform: full-width, the ASCII characters as their full-width forms, two columns each
                'full-width' => preg_replace_callback('/[!-~]/', static fn (array $m) => mb_chr(\ord($m[0]) + 0xFEE0) ?: $m[0], $text) ?? $text,
                'full-size-kana' => self::toFullSizeKana($text),
                'small-caps' => self::toSmallCaps($text),
                'diagonal-fractions' => self::toFractions($text),
                // Like font-variant-numeric: ordinal, the ordinal suffix right after a number in superscript, like 1st,
                // 2nd, 1er or 2e; not the units, like 10px
                'ordinal' => preg_replace_callback('/(?<=\d)(?:st|nd|rd|th|er|re|e|o|a)(?![\p{L}\d])/u', static fn (array $m) => self::toScript($m[0], 'super'), $text), // codespell:ignore nd
                // A combining long solidus over each zero, like font-variant-numeric: slashed-zero
                'slashed-zero' => str_replace('0', "0\u{0338}", $text),
                default => $text,
            } ?? $text;
        }

        if ('' === $text) {
            return;
        }
        // Transparent, like opacity: 0: no-break spaces as wide as the text, which keeps its room
        if (array_any($styles, static fn (?Style $style) => true === $style?->getTransparent())) {
            $text = implode('', array_map(static fn (string $grapheme) => ' ' === $grapheme ? $grapheme : str_repeat("\u{00A0}", max(1, mb_strwidth($grapheme))), grapheme_str_split($text) ?: [$text]));
        }
        if (null !== $script) {
            $text = self::toScript($text, $script);
        }
        // hyphens: auto, a soft hyphen where the patterns of the language break a word, unless it has its own
        if ($this->block->hyphens && null !== $hyphenator = $this->hyphenator) {
            $text = preg_replace_callback('/[\p{L}\p{M}\x{00AD}]+/u', static function (array $m) use ($hyphenator): string {
                if (\strlen($m[0]) > 4 * Hyphenator::MAX_LENGTH || str_contains($m[0], "\u{00AD}") || [] === $breaks = $hyphenator->hyphenate($m[0])) {
                    return $m[0];
                }
                $breaks = array_flip($breaks);
                $word = '';
                foreach (mb_str_split($m[0]) as $i => $character) {
                    $word .= (isset($breaks[$i]) ? "\u{00AD}" : '').$character;
                }

                return $word;
            }, $text) ?? $text;
        }
        if (!$this->block->hyphens) {
            $text = str_replace("\u{00AD}", '', $text);
        } elseif (null !== $limits = $this->block->hyphenLimits) {
            // hyphenate-limit-chars: the soft hyphens of words too short, or too close to their ends, do not break them
            $text = preg_replace_callback('/[^\s\x{00AD}]*\x{00AD}\S*/u', static fn (array $m) => self::limitHyphens($m[0], ...$limits), $text) ?? $text;
        }
        // word-break: break-all, a line can break between any two characters: a zero-width space between them
        if ($this->block->breakAll && 'pre' !== $whiteSpace && 'nowrap' !== $whiteSpace) {
            $text = preg_replace_callback('/\S{2,}/u', static fn (array $m) => implode("\u{200B}", grapheme_str_split($m[0]) ?: [$m[0]]), $text) ?? $text;
        }
        // word-break: keep-all, the letters of a Chinese, Japanese or Korean word stay together, like the letters of other
        // scripts: a word joiner between them, lines breaking at spaces and punctuation
        elseif ($this->block->keepAll && 'pre' !== $whiteSpace && 'nowrap' !== $whiteSpace) {
            $text = preg_replace_callback('/'.self::CJK_LETTER.'{2,}/u', static fn (array $m) => implode(self::WORD_JOINER, grapheme_str_split($m[0]) ?: [$m[0]]), $text) ?? $text;
            // A word across inline elements, like 文章<b>を書</b>きます: joined to the letter before it too
            if (preg_match('/^'.self::CJK_LETTER.'/u', $text) && preg_match('/'.self::CJK_LETTER.'(?:\x1b\[[0-9;:]*[A-Za-z]|\x1b\][^\x07]*\x07)*$/u', mb_strcut($state['text'], max(0, \strlen($state['text']) - 256)))) {
                $text = self::WORD_JOINER.$text;
            }
        }
        // Tab stops every tab-size columns, from the start of the line
        if (str_contains($text, "\t") && ('pre' === $whiteSpace || 'pre-wrap' === $whiteSpace)) {
            $line = substr($state['text'], (int) strrpos("\n".$state['text'], "\n"));
            $column = AnsiUtils::visibleWidth($line);
            $expanded = '';
            foreach (preg_split('/(\t|\n)/', $text, -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
                if ("\t" === $part) {
                    $spaces = $this->block->tabSize - $column % $this->block->tabSize;
                    $expanded .= str_repeat(' ', $spaces);
                    $column += $spaces;
                } elseif ("\n" === $part) {
                    $expanded .= "\n";
                    $column = 0;
                } else {
                    $expanded .= $part;
                    $column += AnsiUtils::visibleWidth($part);
                }
            }
            $text = $expanded;
        }
        // Like text-autospace: a space between ideographs and letters or digits, the end of the text before included
        if ([] !== $this->block->autospace && 'pre' !== $whiteSpace) {
            $text = self::autospace($text, $this->block->autospace);
            if (!$space && '' !== $state['text'] && preg_match('/\X$/u', AnsiUtils::stripAnsiCodes($state['text']), $m) && '' !== self::autospace($m[0].mb_substr($text, 0, 1), $this->block->autospace, true)) {
                $text = ' '.$text;
            }
        }
        if ($letterSpacing > 0) {
            // Like letter-spacing: room after each character but the last of the run; the cells of an image kept together
            $pieces = [];
            foreach (grapheme_str_split($text) ?: [$text] as $grapheme) {
                $cell = 1 === preg_match('/^[\x{100000}-\x{10FFFF}]$/u', $grapheme);
                if ($cell && $pieces && preg_match('/[\x{100000}-\x{10FFFF}]$/u', $pieces[\count($pieces) - 1])) {
                    $pieces[\count($pieces) - 1] .= $grapheme;
                } else {
                    $pieces[] = $grapheme;
                }
            }
            $text = implode(str_repeat(' ', $letterSpacing), $pieces);
        }
        if ($space) {
            // Styled like the run it precedes, so that a background has no gaps
            $text = ' '.$text;
        }
        // After an inline block, a line can break before what follows, unless at a space already
        if ($state['breakAfter'] ?? false) {
            $state['breakAfter'] = false;
            if (!preg_match('/^[ \t\n]/', $text)) {
                $state['text'] .= "\u{200B}";
            }
        }
        // Like word-spacing: more room at each space between words, those of the text only
        if ($words && $wordSpacing > 0) {
            $text = str_replace(' ', str_repeat(' ', 1 + $wordSpacing), $text);
        }

        $styled = static fn (string $part, array $partStyles): string => '' === $part || !$partStyles ? $part : (1 === \count($partStyles) ? $partStyles[0] : Style::mergeAll($partStyles))->apply($part);
        $state['spaced'] = false;
        // Something drawn on the line already, which an inline block can break after
        $state['drawn'] = ($state['drawn'] ?? false) || '' !== trim($text);

        // Like ::first-letter: the first letter of the block, with the punctuation before it
        if (null !== $this->block->firstLetter && !$state['firstLetter'] && preg_match('/^(\s*)(\p{P}*\X)(.*)$/su', $text, $m)) {
            $state['firstLetter'] = true;
            $this->put($state, $m[1], $styles, $styled);
            // Like initial-letter: a drop cap, drawn beside the lines
            if (null !== $this->block->initialLetter && '' === trim($state['text'])) {
                $this->dropCap = $m[2];
                // The space after the letter is not drawn, the text starting beside it; the one after a word is
                if ('' === ltrim($m[3])) {
                    $state['pendingSpace'] = false;
                }
                $this->put($state, ltrim($m[3]), $styles, $styled);

                return;
            }
            $this->put($state, $m[2], [...$styles, $this->block->firstLetter], $styled);
            $this->put($state, $m[3], $styles, $styled);

            return;
        }

        $this->put($state, $text, $styles, $styled);
    }

    /**
     * Adds a styled part, the columns of it still on the first line with the ::first-line style under its own.
     *
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     * @param list<Style>                                                                                                                                                                                                  $styles
     * @param \Closure(string, list<Style>): string                                                                                                                                                                        $styled
     */
    private function put(array &$state, string $part, array $styles, \Closure $styled): void
    {
        if ($state['firstLine'] <= 0 || null === $this->block->firstLine || '' === $part) {
            $state['text'] .= $styled($part, $styles);

            return;
        }

        $head = '';
        foreach (grapheme_str_split($part) ?: [$part] as $grapheme) {
            if ($state['firstLine'] <= 0) {
                break;
            }
            $head .= $grapheme;
            $state['firstLine'] -= AnsiUtils::graphemeWidth($grapheme);
        }
        $lineStyles = $styles;
        array_splice($lineStyles, min($state['firstLineAt'], \count($lineStyles)), 0, [$this->block->firstLine]);
        $state['text'] .= $styled($head, $lineStyles).$styled(substr($part, \strlen($head)), $styles);
    }

    /**
     * Writes a run with superscript or subscript characters, when all of them have one.
     *
     * Unicode has them for digits, signs and some letters: x², H₂O. A run
     * with a character that has none is left on the line, like before.
     *
     *
     * Adds styled text as is: a pending space first, like emit().
     *
     * @param array{text: string, index: int, pendingSpace: bool, firstLetter: bool, spaced: bool, link: int, firstLine: int, firstLineAt: int, atomic?: int, drawn?: bool, breakAfter?: bool, pendingMarks?: list<Style>} $state
     */
    private function emitRaw(array &$state, string $text): void
    {
        if ($state['pendingSpace'] && '' !== $state['text'] && !$state['spaced']) {
            $state['text'] .= ' ';
        }
        $state['pendingSpace'] = false;
        $state['spaced'] = false;
        $state['text'] .= $text;
        $state['drawn'] = ($state['drawn'] ?? false) || '' !== trim(AnsiUtils::stripAnsiCodes($text));
        $state['firstLine'] = max(0, $state['firstLine'] - AnsiUtils::visibleWidth($text));
    }

    /**
     * Puts a space between ideographs and the letters or digits next to them, like text-autospace.
     *
     * @param list<'alpha'|'numeric'> $kinds
     * @param bool                    $test  Whether to return only the spaces put, '' for none
     */
    private static function autospace(string $text, array $kinds, bool $test = false): string
    {
        $ideograph = '[\p{Han}\p{Hiragana}\p{Katakana}\p{Bopomofo}]';
        // Not the full-width forms, which are ideographic in width
        $other = '(?![\x{FF00}-\x{FFEF}])['.(\in_array('alpha', $kinds, true) ? '\p{Latin}\p{Greek}\p{Cyrillic}' : '').(\in_array('numeric', $kinds, true) ? '\p{Nd}' : '').']'; // codespell:ignore nd
        if ('(?![\x{FF00}-\x{FFEF}])[]' === $other) {
            return $test ? '' : $text;
        }
        $spaced = preg_replace('/(?<='.$ideograph.')(?='.$other.')|(?<='.$other.')(?='.$ideograph.')/u', ' ', $text) ?? $text;
        if ($test) {
            return $spaced === $text ? '' : ' ';
        }

        return $spaced;
    }

    /**
     * Small kana as their full-size forms, like text-transform: full-size-kana, for ruby annotations.
     */
    private static function toFullSizeKana(string $text): string
    {
        static $map = null;
        $map ??= array_combine(
            mb_str_split('ぁぃぅぇぉゕゖっゃゅょゎァィゥェォヵㇰヶㇱㇲッㇳㇴㇵㇶㇷㇸㇹㇺャュョㇻㇼㇽㇾㇿヮｧｨｩｪｫｯｬｭｮ𛄲𛅐𛅑𛅒𛅕𛅤𛅥𛅦𛅧'),
            mb_str_split('あいうえおかけつやゆよわアイウエオカクケシスツトヌハヒフヘホムヤユヨラリルレロワｱｲｳｴｵﾂﾔﾕﾖこゐゑをコヰヱヲン'),
        );

        return strtr($text, $map);
    }

    /**
     * Lowercase letters as small capitals, like font-variant: small-caps; those without one stay.
     */
    private static function toSmallCaps(string $text): string
    {
        static $map = null;
        $map ??= array_combine(mb_str_split('abcdefghijklmnopqrstuvwxyz'), mb_str_split('ᴀʙᴄᴅᴇꜰɢʜɪᴊᴋʟᴍɴᴏᴘǫʀꜱᴛᴜᴠᴡxʏᴢ'));

        return strtr($text, $map);
    }

    /**
     * Fractions of digits as the vulgar fraction characters (½), or with superscript and subscript digits around a
     * fraction slash (⁵⁄₁₆), like font-variant-numeric: diagonal-fractions; dates like 12/25/2026 stay.
     */
    private static function toFractions(string $text): string
    {
        static $vulgar = ['1/2' => '½', '1/3' => '⅓', '2/3' => '⅔', '1/4' => '¼', '3/4' => '¾', '1/5' => '⅕', '2/5' => '⅖', '3/5' => '⅗', '4/5' => '⅘', '1/6' => '⅙', '5/6' => '⅚', '1/7' => '⅐', '1/8' => '⅛', '3/8' => '⅜', '5/8' => '⅝', '7/8' => '⅞', '1/9' => '⅑', '1/10' => '⅒', '0/3' => '↉'];

        return preg_replace_callback('#(?<![\d/])(\d+)/(\d+)(?![\d/])#', static fn (array $m) => $vulgar[$m[0]] ?? self::toScript($m[1], 'super')."\u{2044}".self::toScript($m[2], 'sub'), $text) ?? $text;
    }

    private static function toScript(string $text, string $script): string
    {
        static $super = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹', '+' => '⁺', '-' => '⁻', '=' => '⁼', '(' => '⁽', ')' => '⁾', 'a' => 'ᵃ', 'b' => 'ᵇ', 'c' => 'ᶜ', 'd' => 'ᵈ', 'e' => 'ᵉ', 'f' => 'ᶠ', 'g' => 'ᵍ', 'h' => 'ʰ', 'i' => 'ⁱ', 'j' => 'ʲ', 'k' => 'ᵏ', 'l' => 'ˡ', 'm' => 'ᵐ', 'n' => 'ⁿ', 'o' => 'ᵒ', 'p' => 'ᵖ', 'r' => 'ʳ', 's' => 'ˢ', 't' => 'ᵗ', 'u' => 'ᵘ', 'v' => 'ᵛ', 'w' => 'ʷ', 'x' => 'ˣ', 'y' => 'ʸ', 'z' => 'ᶻ', ' ' => ' '];
        static $sub = ['0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉', '+' => '₊', '-' => '₋', '=' => '₌', '(' => '₍', ')' => '₎', 'a' => 'ₐ', 'e' => 'ₑ', 'h' => 'ₕ', 'i' => 'ᵢ', 'j' => 'ⱼ', 'k' => 'ₖ', 'l' => 'ₗ', 'm' => 'ₘ', 'n' => 'ₙ', 'o' => 'ₒ', 'p' => 'ₚ', 'r' => 'ᵣ', 's' => 'ₛ', 't' => 'ₜ', 'u' => 'ᵤ', 'v' => 'ᵥ', 'x' => 'ₓ', ' ' => ' '];
        $map = 'super' === $script ? $super : $sub;

        $characters = mb_str_split($text);
        foreach ($characters as $character) {
            if (!isset($map[$character])) {
                return $text;
            }
        }

        return implode('', array_map(static fn (string $character) => $map[$character], $characters));
    }

    /**
     * @param list<InlineNode> $nodes
     */
    private static function countElements(array $nodes): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if (!$node->isText()) {
                $count += 1 + self::countElements($node->children);
            }
        }

        return $count;
    }

    /**
     * A link wrapped over lines is closed at the end of each and opened again at the start of the next: every line
     * stands alone, drawn or diffed apart.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function carryLinks(array $lines): array
    {
        $open = null;
        foreach ($lines as $i => $line) {
            if (null !== $open) {
                $line = $open.$line;
            }
            $open = null;
            if (str_contains($line, "\x1b]8;") && preg_match_all('/\x1b\]8;[^;\x07\x1b]*;([^\x07\x1b]*)\x07/', $line, $matches, \PREG_SET_ORDER)) {
                $last = end($matches);
                if ('' !== $last[1]) {
                    $open = $last[0];
                    $line .= "\x1b]8;;\x07";
                }
            }
            $lines[$i] = $line;
        }

        return $lines;
    }

    /**
     * The OSC 8 sequence that opens a link drawn by a widget, like the links of a text: to a URL the terminal follows,
     * or to a fragment of the page; null for an href that is neither.
     *
     * @internal
     */
    public static function linkOpening(string $href, string $id): ?string
    {
        if (null !== $target = self::linkTarget($href)) {
            return "\x1b]8;id=".$id.';'.$target."\x07";
        }
        if (null !== $fragment = self::fragmentTarget($href)) {
            return "\x1b]8;".self::FRAGMENT_LINK.':id='.$id.';'.$fragment."\x07";
        }

        return null;
    }

    /**
     * A link to a fragment of the page: #id, the id printable and without a separator of the sequence.
     */
    private static function fragmentTarget(string $href): ?string
    {
        $href = trim($href);

        return preg_match('/^#[^\x00-\x20\x7f;\e]{0,256}$/', $href) ? $href : null;
    }

    /**
     * Keeps the soft hyphens of a word with at least $word characters, $before before them and $after after them.
     */
    private static function limitHyphens(string $text, int $word, int $before, int $after): string
    {
        $graphemes = grapheme_str_split($text) ?: [$text];
        $total = \count(array_filter($graphemes, static fn (string $g) => "\u{00AD}" !== $g));
        $out = '';
        $seen = 0;
        foreach ($graphemes as $grapheme) {
            if ("\u{00AD}" !== $grapheme) {
                ++$seen;
                $out .= $grapheme;
            } elseif ($total >= $word && $seen >= $before && $total - $seen >= $after) {
                $out .= $grapheme;
            }
        }

        return $out;
    }

    private function resolve(string $href): string
    {
        return null !== $this->baseUrl ? UrlResolver::resolve($href, $this->baseUrl) : $href;
    }

    private static function linkTarget(string $href): ?string
    {
        $href = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8(trim($href)));

        return preg_match(self::LINK_SCHEMES, $href) ? $href : null;
    }

    /**
     * Whether a line holds one word only, its text without its styles.
     */
    private static function singleWord(string|false $line): bool
    {
        return false !== $line && 1 === \count(preg_split('/[ \t\n\r\f]+/', trim(AnsiUtils::stripAnsiCodes($line)), -1, \PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
