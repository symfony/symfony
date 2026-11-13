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
use Symfony\Component\Tui\Event\CollapseEvent;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Event\ToggleEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * A collapsible panel with a summary header and expandable content.
 *
 * Mirrors the HTML <details>/<summary> pattern. The summary is always
 * visible; the content is shown only when expanded.
 *
 * Usage:
 *
 *     // Basic
 *     $collapsible = new CollapsibleWidget('Settings', new TextWidget('Content…'));
 *
 *     // With a description (right-aligned in the header)
 *     $collapsible = new CollapsibleWidget('Settings', $list, description: '[3 items]');
 *
 *     // Listen to events
 *     $collapsible->on(ExpandEvent::class, fn () => ...);
 *     $collapsible->on(CollapseEvent::class, fn () => ...);
 *
 * Default keybindings: Enter/Space to toggle, Right to expand, Left to collapse.
 *
 * The header parts are styleable via the stylesheet, with an optional
 * :focus state:
 *
 *     $stylesheet->addRule(CollapsibleWidget::class.'::symbol', new Style(bold: true));
 *     $stylesheet->addRule(CollapsibleWidget::class.'::summary:focus', new Style(bold: true));
 *     $stylesheet->addRule(CollapsibleWidget::class.'::description', new Style(color: 'gray'));
 *
 * @experimental
 *
 * @author Sylvain Blondeau <contact@sylvainblondeau.dev>
 */
class CollapsibleWidget extends AbstractWidget implements FocusableInterface, ParentInterface, ActivatableInterface, PartsInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    /** @var array<string, string> */
    private array $summaryAttributes;
    private ?Style $summaryStyle = null;
    /** @var array{?string, ?string, bool} The content before and after the summary, like summary::after, and whether the latter is at the end of the line */
    private array $summaryContent = [null, null, false];
    private string $summaryEnd = '';
    private bool $summaryHidden = false;
    private ?string $marker = null;

    public function __construct(
        private string $summary,
        private AbstractWidget $content,
        private bool $expanded = false,
        private ?string $description = null,
        private readonly string $collapsedSymbol = '▶',
        private readonly string $expandedSymbol = '▼',
    ) {
        $this->summaryAttributes = [];
        if ($expanded) {
            $this->attachChild($content);
        }
        $this->setState('open', $expanded);
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * The attributes of the summary, which a style provider matches like a <summary>.
     *
     * @param array<string, string> $attributes
     *
     * @return $this
     */
    public function setSummaryAttributes(array $attributes): static
    {
        $this->summaryAttributes = $attributes;
        $this->invalidate();

        return $this;
    }

    public function getParts(): array
    {
        return ['summary' => ['summary', $this->summaryAttributes]];
    }

    /**
     * The style of the summary, and the content of its marker: the symbol, or none.
     */
    public function setPartStyle(string $part, ?Style $style, ?string $marker): void
    {
        $marker = null !== $marker ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($marker)) : null;
        // Computed again each cascade: equal values change nothing
        if ('summary' !== $part || ($style == $this->summaryStyle && $marker === $this->marker)) {
            return;
        }
        $this->summaryStyle = $style;
        $this->marker = $marker;
        $this->invalidate();
    }

    /**
     * Sets whether the summary is drawn, like CSS summary { display: none }: its content alone then, while open.
     */
    public function setSummaryHidden(bool $hidden): void
    {
        if ($hidden !== $this->summaryHidden) {
            $this->summaryHidden = $hidden;
            $this->invalidate();
        }
    }

    /**
     * Sets the text drawn at the end of the line of the summary, like what floats at its right.
     */
    public function setSummaryEnd(string $text): static
    {
        $text = trim(StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text)));
        if ($text !== $this->summaryEnd) {
            $this->summaryEnd = $text;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Sets the content drawn before and after the summary, like CSS summary::before and summary::after, the latter
     * at the end of the line when $apart, like a "+" in a summary of display: flex; justify-content: space-between.
     */
    public function setPartContent(string $part, ?string $before, ?string $after, bool $apart = false): void
    {
        $clean = static fn (?string $text): ?string => null !== $text ? StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text)) : null;
        $content = [$clean($before), $clean($after), $apart];
        if ('summary' !== $part || $content === $this->summaryContent) {
            return;
        }
        $this->summaryContent = $content;
        $this->invalidate();
    }

    /**
     * @return $this
     */
    public function setSummary(string $summary): static
    {
        if ($this->summary !== $summary) {
            $this->summary = $summary;
            $this->invalidate();
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return $this
     */
    public function setDescription(?string $description): static
    {
        if ($this->description !== $description) {
            $this->description = $description;
            $this->invalidate();
        }

        return $this;
    }

    public function getContent(): AbstractWidget
    {
        return $this->content;
    }

    /**
     * Replace the content widget.
     *
     * When expanded, the old content leaves the widget tree and the new
     * one takes its place.
     *
     * @return $this
     */
    public function setContent(AbstractWidget $content): static
    {
        if ($content === $this->content) {
            return $this;
        }

        if ($this->expanded) {
            $this->detachChild($this->content);
        }
        $this->content = $content;
        if ($this->expanded) {
            $this->attachChild($content);
        }
        $this->invalidate();

        return $this;
    }

    public function isExpanded(): bool
    {
        return $this->expanded;
    }

    public function toggle(): void
    {
        $this->expanded = !$this->expanded;
        if ($this->expanded) {
            $this->attachChild($this->content);
            $this->dispatch(new ExpandEvent($this));
        } else {
            $this->detachChild($this->content);
            $this->dispatch(new CollapseEvent($this));
        }
        $this->setState('open', $this->expanded);
        $this->invalidate();
        // Once the state is the new one, like the toggle event of a <details>
        $this->dispatch(new ToggleEvent($this, $this->expanded ? 'closed' : 'open', $this->expanded ? 'open' : 'closed'));
    }

    public function expand(): void
    {
        if (!$this->expanded) {
            $this->toggle();
        }
    }

    public function collapse(): void
    {
        if ($this->expanded) {
            $this->toggle();
        }
    }

    public function all(): array
    {
        return $this->expanded ? [$this->content] : [];
    }

    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        // The marker of the summary: the symbol, what summary::marker gives, or none (list-style: none)
        $symbol = $this->marker ?? ($this->expanded ? $this->expandedSymbol : $this->collapsedSymbol);
        [$before, $after, $apart] = $this->summaryContent;
        // What floats at the right of the summary, like the source link of a method of rustdoc: at the end of the line
        if ('' !== $this->summaryEnd) {
            $after = $this->summaryEnd.($apart && null !== $after && '' !== $after ? ' '.$after : '');
            $text = ($before ?? '').$this->summary.(!$this->summaryContent[2] ? $this->summaryContent[1] ?? '' : '');
            $apart = true;
        } else {
            $text = ($before ?? '').$this->summary.($apart ? '' : $after ?? '');
        }
        $summary = $this->applyElement('summary', null !== $this->summaryStyle ? $this->summaryStyle->apply($text) : $text);
        $header = '' === $symbol ? $summary : $this->applyElement('symbol', $symbol).(null !== $this->marker ? '' : ' ').$summary;
        // The content after it at the end of the line, like the "+" of a summary of justify-content: space-between
        if ($apart && null !== $after && '' !== $after) {
            $after = $this->applyElement('summary', null !== $this->summaryStyle ? $this->summaryStyle->apply($after) : $after);
            $header = AnsiUtils::truncateToWidth($header, max(0, $columns - AnsiUtils::visibleWidth($after) - 1)).str_repeat(' ', max(1, $columns - AnsiUtils::visibleWidth($header) - AnsiUtils::visibleWidth($after))).$after;
        }
        $description = null !== $this->description ? $this->applyElement('description', $this->description) : null;

        $lines = [];

        // A summary of display: none, like those of the docs of rustdoc without scripts: its content alone, when open
        if ($this->summaryHidden) {
            $header = null;
            $description = null;
        }
        if (null === $header) {
            // Not drawn
        } elseif (null !== $description && ($spacing = $columns - AnsiUtils::visibleWidth($header) - AnsiUtils::visibleWidth($description)) >= 1) {
            $lines[] = $header.str_repeat(' ', $spacing).$description;
        } else {
            $lines[] = AnsiUtils::truncateToWidth($header, $columns);
            if (null !== $description) {
                $indent = str_repeat(' ', AnsiUtils::visibleWidth($symbol) + 1);
                $lines[] = AnsiUtils::truncateToWidth($indent.$description, $columns);
            }
        }

        if ($this->expanded && null !== $widgetContext = $this->getContext()) {
            $contentContext = $context->withRows(max(0, $context->getRows() - \count($lines)));
            array_push($lines, ...$widgetContext->renderWidget($this->content, $contentContext));
        }

        return $lines;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        $kb = $this->getKeybindings();
        if ($kb->matches($data, 'expand')) {
            $this->expand();
        } elseif ($kb->matches($data, 'collapse')) {
            $this->collapse();
        } elseif ($kb->matches($data, 'toggle')) {
            $this->toggle();
        }
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'toggle' => [Key::ENTER, Key::SPACE],
            'expand' => [Key::RIGHT],
            'collapse' => [Key::LEFT],
        ];
    }

    public function activate(): bool
    {
        $this->toggle();

        return true;
    }
}
