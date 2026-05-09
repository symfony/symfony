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

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;
use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Markdown\DarkTerminalTheme;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Tempest\Highlight\Highlighter;

/**
 * Renders markdown text with styling using league/commonmark and tempest/highlight.
 *
 * Supports headings, bold, italic, code, lists, links, blockquotes, tables, and horizontal rules.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MarkdownWidget extends AbstractWidget
{
    private MarkdownParser $parser;
    private Highlighter $highlighter;

    /**
     * ANSI codes to restore the context's style after inline style overrides.
     *
     * When the Markdown widget is rendered inside a styled context (e.g. gray italic
     * for thinking blocks), inline styles (yellow for code, bold for strong, etc.)
     * emit reset codes that cancel the context's attributes. This restore sequence
     * re-applies the context's formatting after each inline style.
     */
    private string $restoreContext = '';

    public function __construct(
        private string $text = '',
        ?MarkdownParser $parser = null,
        ?Highlighter $highlighter = null,
    ) {
        if (!class_exists(MarkdownParser::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" as the CommonMark package is not installed. Try running "composer require league/commonmark".', __CLASS__));
        }

        $this->text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
        if (null === $parser) {
            $environment = new Environment();
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $parser = new MarkdownParser($environment);
        }
        $this->parser = $parser;

        if (null === $highlighter && !class_exists(Highlighter::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" as the Tempest Highlight package is not installed. Try running "composer require tempest/highlight".', __CLASS__));
        }
        $this->highlighter = $highlighter ?? new Highlighter(new DarkTerminalTheme());
    }

    /**
     * @return $this
     */
    public function setText(string $text): static
    {
        $this->text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
        $this->invalidate();

        return $this;
    }

    /**
     * Get the markdown text.
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
        if ('' === trim($this->text)) {
            return [];
        }

        return $this->renderMarkdown($context);
    }

    /**
     * @return string[]
     */
    private function renderMarkdown(RenderContext $context): array
    {
        // Context already has inner dimensions (chrome subtracted by the Renderer)
        $contentColumns = $context->getColumns();

        // Compute the restore sequence from the context's resolved style.
        // When the context has styling (e.g. gray italic for thinking blocks),
        // inline styles (yellow for code, bold for strong, etc.) emit reset codes
        // that cancel the context's attributes. This restore sequence re-applies them.
        $this->restoreContext = $context->getStyle()->getAnsiRestore();

        // Parse markdown to AST
        $document = $this->parser->parse($this->text);

        // Render AST to styled lines
        $renderedLines = $this->renderDocument($document, $contentColumns);

        // Wrap all lines to ensure they fit within width
        $wrappedLines = [];
        foreach ($renderedLines as $line) {
            array_push($wrappedLines, ...TextWrapper::wrapTextWithAnsi($line, $contentColumns));
        }

        return $wrappedLines;
    }

    /**
     * @return string[]
     */
    private function renderDocument(Document $document, int $columns): array
    {
        $lines = [];
        $isFirst = true;

        foreach ($document->children() as $child) {
            // Add spacing between blocks
            if (!$isFirst && !$child instanceof TableRow) {
                $lines[] = '';
            }
            $isFirst = false;

            $blockLines = $this->renderNode($child, $columns);
            array_push($lines, ...$blockLines);
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function renderNode(Node $node, int $columns): array
    {
        return match (true) {
            $node instanceof Heading => $this->renderHeading($node, $columns),
            $node instanceof Paragraph => $this->renderParagraph($node, $columns),
            $node instanceof FencedCode => $this->renderFencedCode($node, $columns),
            $node instanceof IndentedCode => $this->renderIndentedCode($node, $columns),
            $node instanceof BlockQuote => $this->renderBlockQuote($node, $columns),
            $node instanceof ListBlock => $this->renderList($node, $columns),
            $node instanceof ThematicBreak => [$this->resolveElement('hr')->apply(str_repeat('─', $columns))],
            $node instanceof Table => $this->renderTable($node, $columns),
            default => $this->renderGenericBlock($node, $columns),
        };
    }

    /**
     * @return string[]
     */
    private function renderHeading(Heading $heading, int $columns): array
    {
        $level = $heading->getLevel();
        $text = $this->renderInlineNodes($heading);
        $prefix = str_repeat('#', $level).' ';

        $styledText = $this->resolveElement('heading')->apply($prefix.$text);

        return TextWrapper::wrapTextWithAnsi($styledText, $columns);
    }

    /**
     * @return string[]
     */
    private function renderParagraph(Paragraph $paragraph, int $columns): array
    {
        $text = $this->renderInlineNodes($paragraph);

        return TextWrapper::wrapTextWithAnsi($text, $columns);
    }

    /**
     * @return string[]
     */
    private function renderFencedCode(FencedCode $code, int $columns): array
    {
        $language = $code->getInfoWords()[0] ?? null;
        $content = rtrim($code->getLiteral(), "\n");

        return $this->renderCodeBlock($content, $language, $columns);
    }

    /**
     * @return string[]
     */
    private function renderIndentedCode(IndentedCode $code, int $columns): array
    {
        $content = rtrim($code->getLiteral(), "\n");

        return $this->renderCodeBlock($content, null, $columns);
    }

    /**
     * @return string[]
     */
    private function renderCodeBlock(string $code, ?string $language, int $columns): array
    {
        $lines = [];

        $codeBlockBorderStyle = $this->resolveElement('code-block-border');

        // Top border
        $lines[] = $codeBlockBorderStyle->apply(str_repeat('─', $columns));

        $indent = '  '; // Code block indent
        $availableColumns = max(1, $columns - \strlen($indent));

        // Try syntax highlighting with tempest/highlight
        $highlighted = null;
        if (null !== $language && '' !== $language) {
            try {
                $highlighted = $this->highlighter->parse($code, $language);
            } catch (\Throwable) {
                // Fall back to plain text
            }
        }

        if (null !== $highlighted) {
            foreach (explode("\n", $highlighted) as $line) {
                $padded = AnsiUtils::truncateToWidth($line, $availableColumns, '', true);
                $lines[] = $indent.$padded;
            }
        } else {
            foreach (explode("\n", $code) as $line) {
                $padded = AnsiUtils::truncateToWidth($line, $availableColumns, '', true);
                $lines[] = $indent.$padded;
            }
        }

        // Bottom border
        $lines[] = $codeBlockBorderStyle->apply(str_repeat('─', $columns));

        return $lines;
    }

    /**
     * @return string[]
     */
    private function renderBlockQuote(BlockQuote $quote, int $columns): array
    {
        $lines = [];
        $quoteColumns = max(1, $columns - 2);
        $quoteStyle = $this->resolveElement('quote');
        $quoteBorderStyle = $this->resolveElement('quote-border');

        foreach ($quote->children() as $child) {
            $childLines = $this->renderNode($child, $quoteColumns);
            foreach ($childLines as $line) {
                $styledLine = $quoteStyle->apply($line);
                $border = $quoteBorderStyle->apply('│ ').$this->restoreContext;
                $lines[] = $border.$styledLine;
            }
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function renderList(ListBlock $list, int $columns): array
    {
        $lines = [];
        $itemColumns = max(1, $columns - 2);
        $isOrdered = 'ordered' === $list->getListData()->type;
        $index = $list->getListData()->start ?? 1;
        $listBulletStyle = $this->resolveElement('list-bullet');

        foreach ($list->children() as $item) {
            if (!$item instanceof ListItem) {
                continue;
            }

            $bullet = $listBulletStyle->apply($isOrdered ? $index.'. ' : '• ').$this->restoreContext;

            $content = $this->renderListItemContent($item, $itemColumns);
            foreach ($content as $i => $line) {
                $lines[] = (0 === $i ? $bullet : '  ').$line;
            }

            ++$index;
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function renderListItemContent(ListItem $item, int $columns): array
    {
        $parts = [];

        foreach ($item->children() as $child) {
            if ($child instanceof Paragraph) {
                $text = $this->renderInlineNodes($child);
                $childLines = TextWrapper::wrapTextWithAnsi($text, $columns);
            } else {
                $childLines = $this->renderNode($child, $columns);
            }
            array_push($parts, ...$childLines);
        }

        return $parts;
    }

    /**
     * @return string[]
     */
    private function renderTable(Table $table, int $columns): array
    {
        $headers = [];
        $rows = [];

        foreach ($table->children() as $section) {
            if (!$section instanceof TableSection) {
                continue;
            }

            foreach ($section->children() as $row) {
                if (!$row instanceof TableRow) {
                    continue;
                }

                $cells = [];
                foreach ($row->children() as $cell) {
                    if ($cell instanceof TableCell) {
                        $cells[] = $this->renderInlineNodes($cell);
                    }
                }

                if ($section->isHead()) {
                    $headers = $cells;
                } else {
                    $rows[] = $cells;
                }
            }
        }

        if (!$headers && !$rows) {
            return [];
        }

        return $this->formatTable($headers, $rows, $columns);
    }

    /**
     * @param string[]        $headers
     * @param array<string[]> $rows
     *
     * @return string[]
     */
    private function formatTable(array $headers, array $rows, int $availableColumns): array
    {
        $columnCounts = array_map('count', $rows);
        $columnCounts[] = \count($headers);

        if (0 === $numCols = max($columnCounts)) {
            return [];
        }

        $borderOverhead = 3 * $numCols + 1;
        $minTableWidth = $borderOverhead + $numCols;

        if ($availableColumns < $minTableWidth) {
            // Fall back to simple text rendering
            $lines = [];
            if ($headers) {
                $lines[] = implode(' | ', $headers);
            }
            foreach ($rows as $row) {
                $lines[] = implode(' | ', $row);
            }

            return $lines;
        }

        // Calculate natural widths
        $naturalWidths = [];
        for ($i = 0; $i < $numCols; ++$i) {
            $naturalWidths[$i] = AnsiUtils::visibleWidth($headers[$i] ?? '');
        }

        foreach ($rows as $row) {
            for ($i = 0; $i < $numCols; ++$i) {
                $naturalWidths[$i] = max($naturalWidths[$i] ?? 0, AnsiUtils::visibleWidth($row[$i] ?? ''));
            }
        }

        $totalNaturalWidth = array_sum($naturalWidths) + $borderOverhead;
        $columnWidths = [];

        if ($totalNaturalWidth <= $availableColumns) {
            $columnWidths = $naturalWidths;
        } else {
            $availableForCells = $availableColumns - $borderOverhead;
            $totalNatural = array_sum($naturalWidths);

            foreach ($naturalWidths as $width) {
                $proportion = $totalNatural > 0 ? $width / $totalNatural : 1 / $numCols;
                $columnWidths[] = max(1, (int) floor($proportion * $availableForCells));
            }

            $allocated = array_sum($columnWidths);
            $remaining = $availableForCells - $allocated;
            for ($i = 0; $remaining > 0 && $i < $numCols; ++$i) {
                ++$columnWidths[$i];
                --$remaining;
            }
        }

        $lines = [];

        // Top border
        $topBorderCells = array_map(static fn (int $w) => str_repeat('─', $w), $columnWidths);
        $lines[] = '┌─'.implode('─┬─', $topBorderCells).'─┐';

        // Header row
        if ($headers) {
            $headerCellLines = [];
            for ($i = 0; $i < $numCols; ++$i) {
                $text = $headers[$i] ?? '';
                $headerCellLines[] = $this->wrapCellText($text, $columnWidths[$i]);
            }

            $headerLineCount = max(array_map('count', $headerCellLines));

            for ($lineIdx = 0; $lineIdx < $headerLineCount; ++$lineIdx) {
                $rowParts = [];
                for ($colIdx = 0; $colIdx < $numCols; ++$colIdx) {
                    $text = $headerCellLines[$colIdx][$lineIdx] ?? '';
                    $padded = $text.str_repeat(' ', max(0, $columnWidths[$colIdx] - AnsiUtils::visibleWidth($text)));
                    $rowParts[] = $this->resolveElement('bold')->apply($padded);
                }
                $lines[] = '│ '.implode(' │ ', $rowParts).' │';
            }

            // Separator
            $separatorCells = array_map(static fn (int $w) => str_repeat('─', $w), $columnWidths);
            $lines[] = '├─'.implode('─┼─', $separatorCells).'─┤';
        }

        // Data rows
        foreach ($rows as $row) {
            $rowCellLines = [];
            for ($i = 0; $i < $numCols; ++$i) {
                $text = $row[$i] ?? '';
                $rowCellLines[] = $this->wrapCellText($text, $columnWidths[$i]);
            }

            $rowLineCount = max(array_map('count', $rowCellLines));
            for ($lineIdx = 0; $lineIdx < $rowLineCount; ++$lineIdx) {
                $rowParts = [];
                for ($colIdx = 0; $colIdx < $numCols; ++$colIdx) {
                    $text = $rowCellLines[$colIdx][$lineIdx] ?? '';
                    $rowParts[] = $text.str_repeat(' ', max(0, $columnWidths[$colIdx] - AnsiUtils::visibleWidth($text)));
                }
                $lines[] = '│ '.implode(' │ ', $rowParts).' │';
            }
        }

        // Bottom border
        $bottomBorderCells = array_map(static fn (int $w) => str_repeat('─', $w), $columnWidths);
        $lines[] = '└─'.implode('─┴─', $bottomBorderCells).'─┘';

        return $lines;
    }

    /**
     * @return string[]
     */
    private function wrapCellText(string $text, int $maxWidth): array
    {
        return TextWrapper::wrapTextWithAnsi($text, max(1, $maxWidth));
    }

    /**
     * @return string[]
     */
    private function renderGenericBlock(Node $node, int $columns): array
    {
        $text = $this->renderInlineNodes($node);
        if ('' === $text) {
            return [];
        }

        return TextWrapper::wrapTextWithAnsi($text, $columns);
    }

    /**
     * Render all inline nodes within a container to a single styled string.
     */
    private function renderInlineNodes(Node $container): string
    {
        $result = '';

        foreach ($container->children() as $child) {
            $result .= $this->renderInlineNode($child);
        }

        return $result;
    }

    private function renderInlineNode(Node $node): string
    {
        return match (true) {
            $node instanceof Text => $node->getLiteral(),
            $node instanceof Strong => $this->resolveElement('bold')->apply($this->renderInlineNodes($node)).$this->restoreContext,
            $node instanceof Emphasis => $this->resolveElement('italic')->apply($this->renderInlineNodes($node)).$this->restoreContext,
            $node instanceof Strikethrough => $this->resolveElement('strikethrough')->apply($this->renderInlineNodes($node)).$this->restoreContext,
            $node instanceof Code => $this->resolveElement('code')->apply($node->getLiteral()).$this->restoreContext,
            $node instanceof Link => $this->resolveElement('link')->apply($this->renderInlineNodes($node)).$this->restoreContext.' '.$this->resolveElement('link-url')->apply('('.$node->getUrl().')').$this->restoreContext,
            $node instanceof Newline => "\n",
            default => $this->renderInlineNodes($node), // For nested structures
        };
    }
}
