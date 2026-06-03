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
 * InputWidget::setValue, EditorWidget/EditorDocument, MarkdownWidget,
 * SettingItem::$currentValue, ProgressBarWidget::setMessage,
 * LoaderWidget::setMessage.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TextWidget extends AbstractWidget
{
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
     * @return $this
     */
    public function setText(string $text): static
    {
        $this->text = $text;
        $this->invalidate();

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
     * @return string[]
     */
    private function renderText(RenderContext $context): array
    {
        // Replace tabs with 3 spaces
        $normalizedText = str_replace("\t", '   ', $this->text);

        // Context already has inner dimensions (chrome subtracted by the Renderer)
        $contentColumns = $context->getColumns();

        // Either truncate or wrap based on mode
        if ($this->truncate) {
            $lines = explode("\n", $normalizedText);
            $processedLines = [];
            foreach ($lines as $line) {
                $processedLines[] = AnsiUtils::truncateToWidth($line, $contentColumns);
            }
        } else {
            $processedLines = TextWrapper::wrapTextWithAnsi($normalizedText, $contentColumns);
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
