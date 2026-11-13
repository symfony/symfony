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
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;

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
class CollapsibleWidget extends AbstractWidget implements FocusableInterface, ParentInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    public function __construct(
        private string $summary,
        private AbstractWidget $content,
        private bool $expanded = false,
        private ?string $description = null,
        private readonly string $collapsedSymbol = '▶',
        private readonly string $expandedSymbol = '▼',
    ) {
        if ($expanded) {
            $this->attachChild($content);
        }
    }

    public function getSummary(): string
    {
        return $this->summary;
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
        $this->invalidate();
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
        $symbol = $this->expanded ? $this->expandedSymbol : $this->collapsedSymbol;
        $header = $this->applyElement('symbol', $symbol).' '.$this->applyElement('summary', $this->summary);
        $description = null !== $this->description ? $this->applyElement('description', $this->description) : null;

        $lines = [];

        if (null !== $description && ($spacing = $columns - AnsiUtils::visibleWidth($header) - AnsiUtils::visibleWidth($description)) >= 1) {
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
}
