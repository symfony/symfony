<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Content;

use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\WidgetContext;

/**
 * Explicit native leaf-widget interoperability. Prepare before rendering the document.
 *
 * @experimental
 */
final class NativeContentRegistry
{
    private \SplObjectStorage $widgets;
    private \WeakMap $prepared;
    private bool $changed = false;
    private ?WidgetContext $context = null;
    private ?\WeakReference $parent = null;
    private \SplObjectStorage $attached;
    private ?AbstractWidget $focused = null;

    public function __construct(private TerminalTextMeasurer $metrics = new TerminalTextMeasurer(), private readonly FontRegistry $fonts = new FontRegistry(), private MediaContext $media = new MediaContext())
    {
        $this->widgets = new \SplObjectStorage();
        $this->attached = new \SplObjectStorage();
        $this->prepared = new \WeakMap();
    }

    public function register(\Dom\Element $element, AbstractWidget $widget): void
    {
        if (($this->widgets[$element] ?? null) === $widget) {
            return;
        }
        if ($widget instanceof ParentInterface) {
            throw new \InvalidArgumentException('Only native leaf widgets are supported by this content adapter.');
        }
        if (null !== $widget->getParent() || null !== $widget->getContext()) {
            throw new \InvalidArgumentException('Native content must not belong to another widget tree.');
        }
        foreach ($this->widgets as $registered) {
            if ($registered !== $element && $this->widgets[$registered] === $widget) {
                throw new \InvalidArgumentException('A native widget can replace only one element.');
            }
        }
        if (isset($this->widgets[$element])) {
            $this->detachWidget($this->widgets[$element]);
        }
        $this->widgets[$element] = $widget;
        $this->attachWidget($widget);
        unset($this->prepared[$element]);
        $this->markChanged();
    }

    public function unregister(\Dom\Element $element): void
    {
        if (isset($this->widgets[$element])) {
            $this->detachWidget($this->widgets[$element]);
            unset($this->widgets[$element], $this->prepared[$element]);
            $this->markChanged();
        }
    }

    /** @internal */
    public function attach(AbstractWidget $parent, WidgetContext $context): void
    {
        if (null !== $this->context && ($this->context !== $context || $this->parent?->get() !== $parent)) {
            throw new \LogicException('A native content registry can be mounted in only one document widget.');
        }
        $this->parent = \WeakReference::create($parent);
        $this->context = $context;
        $this->prepared = new \WeakMap();
        $this->markChanged();
        try {
            foreach ($this->widgets as $element) {
                $this->attachWidget($this->widgets[$element]);
            }
        } catch (\Throwable $error) {
            $this->detach();
            throw $error;
        }
    }

    /** @internal */
    public function detach(): void
    {
        foreach (iterator_to_array($this->attached, false) as $widget) {
            $this->detachWidget($widget);
        }
        $this->context = null;
        $this->parent = null;
    }

    public function dispose(): void
    {
        $this->markChanged();
        $this->detach();
        $this->widgets = new \SplObjectStorage();
        $this->prepared = new \WeakMap();
        $this->markChanged();
    }

    public function getWidget(\Dom\Element $element): ?AbstractWidget
    {
        return $this->widgets[$element] ?? null;
    }

    /** @internal */
    public function synchronizeFocus(?\Dom\Element $element): void
    {
        $widget = null === $element ? null : $this->getWidget($element);
        $widget = $widget instanceof FocusableInterface ? $widget : null;
        if ($widget === $this->focused) {
            return;
        }
        $previous = $this->focused;
        $this->focused = $widget;
        if ($previous instanceof FocusableInterface) {
            $previous->setFocused(false);
        }
        if ($widget instanceof FocusableInterface && $this->focused === $widget && null !== $element && $this->getWidget($element) === $widget) {
            $widget->setFocused(true);
        }
    }

    private function markChanged(): void
    {
        $this->changed = true;
        $this->parent?->get()?->invalidate();
        $this->context?->requestRender();
    }

    private function attachWidget(AbstractWidget $widget): void
    {
        if (null === $this->context || isset($this->attached[$widget])) {
            return;
        }
        if (null !== $widget->getParent() || null !== $widget->getContext()) {
            throw new \InvalidArgumentException('Native content must not belong to another widget tree.');
        }
        $manager = $this->context->getFocusManager();
        $index = $manager->getTabIndex($widget);
        $manager->setTabIndex($widget, -1);
        $this->attached[$widget] = $index;
        try {
            $this->context->attachChild($this->parent->get(), $widget);
        } catch (\Throwable $error) {
            $this->detachWidget($widget);
            throw $error;
        }
    }

    private function detachWidget(AbstractWidget $widget): void
    {
        if ($this->focused === $widget) {
            $this->focused = null;
            if ($widget instanceof FocusableInterface) {
                $widget->setFocused(false);
            }
        }
        if (!isset($this->attached[$widget])) {
            return;
        }
        $index = $this->attached[$widget];
        unset($this->attached[$widget]);
        $context = $this->context;
        try {
            $context?->detachChild($widget);
        } finally {
            $context?->getFocusManager()->setTabIndex($widget, $index);
        }
    }

    public function prepare(?TerminalTextMeasurer $metrics = null, ?MediaContext $media = null): bool
    {
        $changed = $this->changed;
        if (null !== $metrics && $metrics != $this->metrics || null !== $media && $media != $this->media) {
            $this->metrics = $metrics ?? $this->metrics;
            $this->media = $media ?? $this->media;
            $this->prepared = new \WeakMap();
            $changed = true;
        }
        $this->changed = false;
        foreach ($this->widgets as $element) {
            $widget = $this->widgets[$element];
            $reference = \WeakReference::create($widget);
            $widget->beforeRender();
            $revision = $widget->getRenderRevision();
            if (($this->prepared[$element]['revision'] ?? null) !== $revision) {
                $changed = true;
                $this->prepared[$element] = ['reference' => $reference, 'revision' => $revision, 'styles' => []];
            }
        }

        return $changed;
    }

    public function resolve(\Dom\Element $element, array $computedStyle): ?ReplacedContentInterface
    {
        if (!isset($this->widgets[$element])) {
            return null;
        }
        if (!isset($this->prepared[$element])) {
            throw new \LogicException('Prepare native content before computing document styles.');
        }
        $entry = $this->prepared[$element];
        $key = hash('xxh128', serialize($computedStyle));
        $adapter = $entry['styles'][$key] ??= new NativeContentAdapter($entry['reference'], $entry['revision'], $computedStyle, $this->metrics, $this->fonts, $this->media);
        // Only the current style is retained: animations must not grow this cache.
        $entry['styles'] = [$key => $adapter];
        $this->prepared[$element] = $entry;

        return $adapter;
    }
}
