<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Internal\NativeStyleProvider;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\DefaultStyleSheet;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\Style as HtmlStyle;
use Symfony\Bridge\TuiHtml\Style\StyleSheet as HtmlStyleSheet;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Component\Tui\Focus\FocusPolicyInterface;
use Symfony\Component\Tui\Render\FrameWriterInterface;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\RendererDecoratorInterface;
use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Style\DefaultStyleSheet as NativeDefaultStyleSheet;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\ParentInterface;

/**
 * Opt-in rendering of HTML facade roots through the standalone engine.
 *
 * @experimental
 */
final class FacadeRenderer implements RendererDecoratorInterface, FocusPolicyInterface
{
    private HtmlStyleSheet $styleSheet;
    private MediaContext $media;
    private \WeakMap $roots;
    private ?\WeakReference $runtime = null;
    private string $defaultForeground = 'default';
    private ?HtmlStyleSheet $nativeStyles = null;
    private int $nativeStylesRevision = -1;
    private int $renderedStyleRevision = -1;
    private readonly \Closure $nativeStyleResolver;

    public function __construct(private RendererInterface $renderer)
    {
        $this->styleSheet = DefaultStyleSheet::create();
        $this->styleSheet->addProvider(new NativeStyleProvider(NativeDefaultStyleSheet::create(), true));
        $this->media = MediaContext::fromEnvironment();
        $this->roots = new \WeakMap();
        $this->nativeStyleResolver = $this->nativeStyle(...);
    }

    public function getStyleSheet(): HtmlStyleSheet
    {
        return $this->styleSheet;
    }

    public function dropResolvedStyles(): void
    {
        $this->renderer->invalidateStyles();
        foreach ($this->roots as $root => $_) {
            $root->invalidate();
        }
    }

    public function bindRuntime(Tui $tui): void
    {
        $this->runtime = \WeakReference::create($tui);
    }

    /**
     * Configures the foreground used for the terminal's default color in facade rendering.
     * Actual terminal themes are not detected; an explicit RGB color is a caller-provided profile.
     */
    public function setDefaultForeground(Color|string|int $color): void
    {
        $color = $color instanceof Color ? $color->toHex() : ('default' === $color ? 'default' : Color::from($color)->toHex());
        if ($color !== $this->defaultForeground) {
            $this->defaultForeground = $color;
            $this->dropResolvedStyles();
            $this->runtime?->get()?->requestRender();
        }
    }

    public function getMediaContext(): MediaContext
    {
        return $this->media;
    }

    public function setMediaContext(MediaContext $media): void
    {
        if ($media != $this->media) {
            $this->media = $media;
            $this->dropResolvedStyles();
            $this->runtime?->get()?->requestRender();
        }
    }

    public function reveal(AbstractWidget $widget): void
    {
        $root = self::rootOf($widget);
        if (null === $root) {
            return;
        }
        $root->getPresentation()->reveal($widget);
        $target = $root->getPresentation()->getWidgetRect($widget);
        while (null !== $target && null !== $frame = $root->getParent()) {
            if (null === $parentRoot = self::rootOf($frame)) {
                break;
            }
            $target = $parentRoot->getPresentation()->revealEmbedded($root, $target);
            $root = $parentRoot;
        }
    }

    public function setImplicitAnchor(AbstractWidget $widget, ?AbstractWidget $anchor): void
    {
        if (null !== $root = self::rootOf($widget)) {
            $root->getPresentation()->setImplicitAnchor($widget, $anchor);
            $root->invalidate();
        }
    }

    public function getFragmentLinkAt(int $row, int $column): ?string
    {
        foreach ($this->roots as $root => $_) {
            $rect = $this->getWidgetRect($root);
            if (null !== $rect && $rect->contains($row, $column) && null !== $target = $root->getPresentation()->getFragmentLinkAt($row - $rect->row, $column - $rect->col)) {
                return $target;
            }
        }

        return null;
    }

    public function getLinkAt(int $row, int $column): ?string
    {
        foreach ($this->roots as $root => $_) {
            if (null !== $rect = $this->getWidgetRect($root)) {
                if (!$rect->contains($row, $column)) {
                    continue;
                }
                if (null !== $link = $root->getPresentation()->getLinkAt($row - $rect->row, $column - $rect->col)) {
                    return $link;
                }
            }
        }

        return null;
    }

    public function getScrollControlAt(int $row, int $column): ?array
    {
        foreach ($this->roots as $root => $_) {
            $rect = $this->getWidgetRect($root);
            if (null !== $rect && $rect->contains($row, $column) && null !== $control = $root->getPresentation()->getScrollControlAt($row - $rect->row, $column - $rect->col)) {
                return $control;
            }
        }

        return null;
    }

    public function getDecoratedRenderer(): RendererInterface
    {
        return $this->renderer;
    }

    public function addStyleSheet(StyleSheet|HtmlStyleSheet $styleSheet): void
    {
        if ($styleSheet instanceof HtmlStyleSheet) {
            $this->styleSheet->merge($styleSheet);
        } else {
            $this->renderer->addStyleSheet($styleSheet);
            $this->styleSheet->addProvider(new NativeStyleProvider(clone $styleSheet));
        }
        $this->dropResolvedStyles();
        $this->runtime?->get()?->requestRender();
    }

    public function resolveElement(AbstractWidget $widget, string $element): Style
    {
        return null === self::rootOf($widget) ? $this->renderer->resolveElement($widget, $element) : StyleBridge::native($this->styleSheet->resolveElement($widget, $element));
    }

    public function invalidateStyles(): void
    {
        $this->renderer->invalidateStyles();
    }

    public function createFrameWriter(TerminalInterface $terminal): FrameWriterInterface
    {
        return new ScreenWriter($terminal);
    }

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect
    {
        $root = self::rootOf($widget);
        if (null === $root) {
            return $this->renderer->getWidgetRect($widget);
        }
        if ($widget === $root) {
            $parent = $root->getParent();
            $parentRoot = null === $parent ? null : self::rootOf($parent);
            if (null === $parentRoot) {
                return $this->renderer->getWidgetRect($root);
            }
            $local = $parentRoot->getPresentation()->getEmbeddedRootRect($root);
            $outer = $this->getWidgetRect($parentRoot);
        } else {
            $local = $root->getPresentation()->getWidgetRect($widget);
            $outer = $this->getWidgetRect($root);
        }
        if (null === $local || null === $outer) {
            return null;
        }
        $rect = $local->withOffset($outer->row, $outer->col);

        return null === $outer->clip ? $rect : $rect->withClip($outer->clip);
    }

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface
    {
        $media = $this->media->withSize($columns, $rows);
        foreach ($this->styleSheet->getProviders() as $provider) {
            if (!$provider instanceof CssStyleSheet) {
                $provider->prepare($root, $media);
            }
        }
        $stylesChanged = $this->renderedStyleRevision !== $this->styleSheet->getRevision();
        $this->renderedStyleRevision = $this->styleSheet->getRevision();
        $this->roots = new \WeakMap();
        $visit = function (AbstractWidget $widget) use (&$visit, $columns, $rows, $stylesChanged): void {
            if ($widget instanceof HtmlRootWidget) {
                $this->roots[$widget] = true;
                $widget->getPresentation()->synchronizeDetachedContent($widget);
                if ($stylesChanged) {
                    $widget->invalidate();
                }
                $widget->setMediaContext($this->media->withSize($columns, $rows));
                $widget->setDefaultForeground($this->defaultForeground);
                $widget->setNativeStyleResolver($this->nativeStyleResolver);
            }
            if ($widget instanceof ParentInterface) {
                foreach ($widget->all() as $child) {
                    $visit($child);
                }
            }
        };
        $visit($root);

        return $this->renderer->renderFrame($root, $columns, $rows);
    }

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface
    {
        return $this->renderer->renderWidgetLines($widget, $context);
    }

    public function resolveStyle(AbstractWidget $widget): Style
    {
        return null === self::rootOf($widget) ? $this->renderer->resolveStyle($widget) : StyleBridge::native($this->styleSheet->resolve($widget, $this->media->columns));
    }

    public function allowsFocus(AbstractWidget $widget): bool
    {
        $root = self::rootOf($widget);
        if (null !== $root && null !== $root->getPresentation()->getResult()) {
            return null !== $this->getWidgetRect($widget);
        }

        return !$this->renderer instanceof FocusPolicyInterface || $this->renderer->allowsFocus($widget);
    }

    public function orderFocusCycle(array $widgets): array
    {
        return $this->renderer instanceof FocusPolicyInterface ? $this->renderer->orderFocusCycle($widgets) : $widgets;
    }

    private function nativeStyle(AbstractWidget $widget, MediaContext $media, bool $resolved = false): HtmlStyle
    {
        if (!$resolved && $this->nativeStylesRevision !== $this->styleSheet->getRevision()) {
            $this->nativeStyles = clone $this->styleSheet;
            foreach ($this->nativeStyles->getProviders() as $provider) {
                if ($provider instanceof CssStyleSheet) {
                    $this->nativeStyles->removeProvider($provider);
                }
            }
            $this->nativeStylesRevision = $this->styleSheet->getRevision();
        }
        $sheet = $resolved ? $this->styleSheet : $this->nativeStyles;

        return $sheet->resolve($widget, $media->columns);
    }

    private static function rootOf(AbstractWidget $widget): ?HtmlRootWidget
    {
        for ($node = $widget; null !== $node; $node = $node->getParent()) {
            if ($node instanceof HtmlRootWidget) {
                return $node;
            }
        }

        return null;
    }
}
