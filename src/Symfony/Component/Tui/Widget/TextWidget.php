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
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Figlet\FigletRenderer;

/**
 * Text component - displays text with word wrapping or truncation.
 *
 * When truncate is false (default), text wraps to multiple lines.
 * When truncate is true, each line is truncated to fit the width with an ellipsis.
 *
 * When a FIGlet font is set via the Style system, the text is rendered as large
 * ASCII art instead. Bundled fonts: big, small, slant, standard, mini.
 * Custom fonts can be registered via the FontRegistry.
 *
 * Font can be set via stylesheet rules, CSS classes, or Tailwind utility classes:
 *
 *     // Stylesheet rule
 *     $stylesheet->addRule('.title', new Style(font: 'big'));
 *
 *     // Tailwind utility class
 *     $widget->addStyleClass('font-big');
 *
 *     // Template
 *     <text class="font-big">Hello</text>
 *
 * ## Raw ANSI passthrough
 *
 * The text content is rendered to the terminal as-is, including any ANSI
 * escape sequences (e.g. SGR color codes). This is intentional: it lets
 * developers compose pre-styled output. Width tracking handles ANSI
 * sequences correctly so wrap/truncate still produce the requested visible
 * width.
 *
 * Because the content is not sanitized, **never pass untrusted input
 * directly to setText() or the constructor**. A hostile string can inject
 * OSC sequences (terminal title, hyperlinks), CSI sequences that hide or
 * reposition surrounding output, or other escape-driven misbehavior. Sanitize
 * upstream (e.g. via {@see Util\StringUtils::stripControlBytes()})
 * before passing user-supplied data here.
 *
 * The same raw-passthrough contract applies to these other surfaces:
 *  - SelectListWidget items (label, description, value)
 *  - SettingItem $label and $description constructor arguments
 *  - InputWidget::setPrompt()
 *
 * Sanitizing widgets (which call StringUtils::stripControlBytes on input):
 * InputWidget::setValue and pasted content, EditorWidget/EditorDocument,
 * MarkdownWidget, SettingItem::$currentValue,
 * ProgressBarWidget::setMessage, LoaderWidget::setMessage.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TextWidget extends AbstractWidget
{
    private string $ellipsis = '...';

    /** @var list<array{int, int, 2?: string, 3?: string}> The columns kept clear at the left and right of each line, the last pair repeating, with what fills the left ones and what follows the line */
    private array $lineInsets = [];
    /** The blank rows above its lines, those of the floats its ::before clears */
    private int $clearance = 0;

    /**
     * @param string $text     Text content to display
     * @param bool   $truncate When true, truncate lines to fit width instead of wrapping
     */
    public function __construct(
        private string $text = '',
        private bool $truncate = false,
    ) {
    }

    /**
     * @return list<array{int, int, 2?: string, 3?: string}>
     *
     * @internal
     */
    public function getLineInsets(): array
    {
        return $this->lineInsets;
    }

    /**
     * Keeps columns clear at the left and right of each line, like text flowing around floats: the lines wrap narrower
     * there, the left ones indented, or filled.
     *
     * @param list<array{int, int, 2?: string, 3?: string}> $insets The left and right columns of each line, the last pair repeating, with what ends the left ones and what follows the text of the line; [] for none
     *
     * @internal
     */
    public function setLineInsets(array $insets): void
    {
        $this->markFloatsSet();
        if ($insets !== $this->lineInsets) {
            $this->lineInsets = $insets;
            $this->clearRenderCache();
        }
    }

    /**
     * @internal
     */
    public function getClearance(): int
    {
        return $this->clearance;
    }

    /**
     * Starts its lines below the floats its ::before clears, like CSS, see Style::withBeforeClear(): the rows they take
     * in its content box blank, its box where it is, its border and background beside them.
     *
     * @internal
     */
    public function setClearance(int $rows): void
    {
        $this->markFloatsSet();
        if ($rows !== $this->clearance) {
            $this->clearance = $rows;
            $this->clearRenderCache();
        }
    }

    /**
     * Drops its insets and clearance once the layout that set them is gone, like when it moved to another parent or its
     * parent changed to a layout without floats, like a flex container: its lines no longer beside them.
     *
     * @internal
     */
    public function dropStaleFloats(): void
    {
        if (([] === $this->lineInsets && 0 === $this->clearance) || $this->floatsSetByLayout()) {
            return;
        }
        $this->lineInsets = [];
        $this->clearance = 0;
        $this->clearRenderCache();
    }

    /**
     * Measures it as if no float were beside it, like its layout among floats does not size it: its insets and its
     * clearance, kept from that layout until it changes, set aside meanwhile, see setLineInsets() and setClearance().
     *
     * @internal
     *
     * @template T
     *
     * @param \Closure(): T $measure
     *
     * @return T
     */
    public function measureWithoutFloats(\Closure $measure): mixed
    {
        if ([] === $this->lineInsets && 0 === $this->clearance) {
            return $measure();
        }
        [$insets, $clearance] = [$this->lineInsets, $this->clearance];
        $this->lineInsets = [];
        $this->clearance = 0;
        // Its render among the floats not taken for the measure, nor the measure for it: that render kept for after it
        try {
            return $this->withoutRenderCache($measure);
        } finally {
            $this->lineInsets = $insets;
            $this->clearance = $clearance;
        }
    }

    /**
     * @return $this
     */
    public function setText(string $text): static
    {
        $this->text = $text;
        $this->invalidate();

        return $this;
    }

    /**
     * Truncate lines that do not fit instead of wrapping them.
     *
     * @param string $ellipsis What ends a truncated line, like "…" or nothing
     *
     * @return $this
     */
    public function setTruncate(bool $truncate, string $ellipsis = '...'): static
    {
        if ($truncate !== $this->truncate || $ellipsis !== $this->ellipsis) {
            $this->truncate = $truncate;
            $this->ellipsis = $ellipsis;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Get the text content.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        // Don't render anything if there's no actual text
        if ('' === $this->text || '' === trim($this->text)) {
            return [];
        }

        $font = $context->getStyle()->getFont();

        if (null !== $font) {
            return $this->renderFiglet($context, $font);
        }

        return $this->renderText($context);
    }

    /**
     * Returns what ends a line broken at a soft hyphen, which subclasses can change.
     */
    protected function getHyphen(): string
    {
        return '-';
    }

    /**
     * Returns the text to draw, which subclasses can compose for the context.
     */
    protected function getDisplayText(RenderContext $context): string
    {
        return $this->text;
    }

    /**
     * @return string[]
     */
    private function renderText(RenderContext $context): array
    {
        $normalizedText = str_replace("\t", str_repeat(' ', AnsiUtils::TAB_WIDTH), $this->getDisplayText($context));

        // Context already has inner dimensions (chrome subtracted by the Renderer)
        $contentColumns = $context->getColumns();

        // Either truncate or wrap based on mode
        if ($this->truncate) {
            $lines = preg_split('/\r\n|\r|\n/', $normalizedText) ?: [$normalizedText];
            $processedLines = [];
            foreach ($lines as $line) {
                $processedLines[] = AnsiUtils::truncateToWidth($line, $contentColumns, $this->ellipsis);
            }
        } elseif ([] !== $insets = $this->lineInsets) {
            $last = \count($insets) - 1;
            // Rows without room are left empty only when a row after them has some: past the floats, the box itself
            $skip = ($past = $contentColumns - $insets[$last][0] - $insets[$last][1]) > 0;
            // Nor rows narrower than a word, up to 12 columns, like browsers, which put a line below the floats where its
            // word does not fit beside them, not a letter on each row; never more than the room past them, which a word
            // wider than the box would never find
            $least = 1;
            // Of floats only, not the decorations of an element cut across lines, which draw on each row
            if ($skip && array_all($insets, static fn (array $inset) => !isset($inset[2]) && !isset($inset[3]))) {
                foreach (preg_split('/\s+/u', AnsiUtils::stripAnsiCodes($normalizedText), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $least = max($least, min(12, $past, AnsiUtils::visibleWidth($word)));
                }
            }
            $processedLines = TextWrapper::wrapTextWithAnsiWidths($normalizedText, static function (int $line) use ($skip, $contentColumns, $insets, $last, $least): int {
                $room = $contentColumns - $insets[min($line, $last)][0] - $insets[min($line, $last)][1];

                return $skip ? ($room < $least ? 0 : $room) : max(1, $room);
            }, $this->getHyphen());
            foreach ($processedLines as $i => $line) {
                $inset = $insets[min($i, $last)];
                $fill = $inset[2] ?? '';
                // Never past the box, like a line given a column when no row had room
                if ('' !== $line && 0 < $left = min($inset[0], $contentColumns - AnsiUtils::visibleWidth($line) - AnsiUtils::visibleWidth($inset[3] ?? ''))) {
                    $line = str_repeat(' ', max(0, $left - AnsiUtils::visibleWidth($fill))).$fill.$line;
                }
                $processedLines[$i] = $line.($inset[3] ?? '');
            }
        } else {
            $processedLines = TextWrapper::wrapTextWithAnsi($normalizedText, $contentColumns, $this->getHyphen());
        }

        return $processedLines ?: [''];
    }

    /**
     * @return string[]
     */
    private function renderFiglet(RenderContext $context, string $fontName): array
    {
        $font = $context->getFontRegistry()->get($fontName);
        $renderer = new FigletRenderer($font);
        $lines = $renderer->render($this->text);

        // Truncate lines that exceed available width (ANSI-aware)
        $truncated = [];
        foreach ($lines as $line) {
            if (AnsiUtils::visibleWidth($line) > $context->getColumns()) {
                $truncated[] = AnsiUtils::truncateToWidth($line, $context->getColumns(), '');
            } else {
                $truncated[] = $line;
            }
        }

        return $truncated;
    }
}
