<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Style\Style as NativeStyle;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Style\Style;

/** @internal */
final class StyleBridge
{
    /** @var \WeakMap<Style, NativeStyle>|null */
    private static ?\WeakMap $nativeStyles = null;

    public static function get(object $source): ?Style
    {
        if (!$source instanceof AbstractWidget) {
            $style = $source->getStyle();

            return $style instanceof Style ? $style : self::computed($style);
        }
        $state = $source->getExtensionState(WidgetState::class);
        $native = $source->getStyle();
        if ($native !== $state->nativeStyle) {
            $state->nativeStyle = $native;
            $state->style = null === $native ? null : self::computed($native);
        }

        return $state->style;
    }

    public static function set(AbstractWidget $widget, ?Style $style): AbstractWidget
    {
        $current = self::get($widget);
        if (null === $style ? null === $current : $style->equals($current)) {
            return $widget;
        }
        $state = $widget->getExtensionState(WidgetState::class);
        $native = null === $style ? null : self::native($style);
        $state->style = $style;
        $state->nativeStyle = $native;
        $widget->setStyle($native);
        $state->nativeStyle = $widget->getStyle();
        $widget->invalidate();

        return $widget;
    }

    public static function native(Style $style): NativeStyle
    {
        self::$nativeStyles ??= new \WeakMap();
        if (null !== $cached = self::$nativeStyles[$style] ?? null) {
            return $cached;
        }
        $native = new NativeStyle(...self::arguments($style, NativeStyle::class));
        self::$nativeStyles[$style] = $native;

        return $native;
    }

    public static function computed(NativeStyle $style): Style
    {
        return new Style(...self::arguments($style, Style::class));
    }

    public static function resolve(?object $source, AbstractWidget $widget): ?Style
    {
        if (null === $source) {
            return null;
        }
        if ($source instanceof \Symfony\Component\Tui\Widget\WidgetContext) {
            $source = $source->getRenderer();
        }

        return $source instanceof \Symfony\Component\TuiHtml\Render\WidgetRendererInterface ? $source->resolveComputedStyle($widget) : self::computed($source->resolveStyle($widget));
    }

    public static function element(object $source, AbstractWidget|string $widget, ?string $element = null): Style
    {
        if ($source instanceof AbstractWidget) {
            $element = $widget;
            $widget = $source;
            $source = $source->getContext()?->getRenderer();
        } elseif ($source instanceof \Symfony\Component\Tui\Widget\WidgetContext) {
            $source = $source->getRenderer();
        }
        if (null === $source) {
            return \Symfony\Component\TuiHtml\Style\DefaultStyleSheet::create()->resolveElement($widget, $element);
        }
        if ($source instanceof \Symfony\Component\TuiHtml\Render\Renderer) {
            return $source->getStyleSheet()->resolveElement($widget, $element);
        }
        $style = $source->resolveElement($widget, $element);

        return $style instanceof Style ? $style : self::computed($style);
    }

    public static function media(?object $source): ?\Symfony\Component\TuiHtml\Style\MediaContext
    {
        if (null === $source) {
            return null;
        }
        if ($source instanceof \Symfony\Component\Tui\Widget\WidgetContext || $source instanceof \Symfony\Component\Tui\Tui) {
            $source = $source->getRenderer();
        }

        return $source instanceof \Symfony\Component\TuiHtml\Render\Renderer ? $source->getMediaContext() : null;
    }

    public static function renderWidget(\Symfony\Component\Tui\Widget\WidgetContext $source, AbstractWidget $widget, \Symfony\Component\TuiHtml\Render\RenderContext $context): array
    {
        $renderer = $source->getRenderer();

        return $renderer instanceof \Symfony\Component\TuiHtml\Render\Renderer ? $renderer->renderLines($widget, $context)->toArray() : $source->renderWidget($widget, ContextBridge::native($context));
    }

    public static function applyElement(AbstractWidget $widget, string $element, string $text): string
    {
        if ('' === $text) {
            return $text;
        }
        $styled = self::element($widget, $element)->apply($text);
        $style = null === $widget->getContext() ? null : self::resolve($widget->getContext(), $widget);

        return $styled !== $text && $style?->isCssCascade() ? $styled.$style->getAnsiRestore() : $styled;
    }

    private static function arguments(object $style, string $target): array
    {
        $arguments = [];
        foreach ((new \ReflectionMethod($target, '__construct'))->getParameters() as $parameter) {
            $getter = 'get'.ucfirst($parameter->name);
            $value = method_exists($style, $getter) ? $style->{$getter}() : $parameter->getDefaultValue();
            $arguments[] = self::value($value, str_contains($target, '\TuiHtml\\') ? 'Symfony\Component\TuiHtml\Style\\' : 'Symfony\Component\Tui\Style\\', $parameter->name);
        }

        return $arguments;
    }

    private static function value(mixed $value, string $namespace, string $property = ''): mixed
    {
        if (!\is_object($value)) {
            return 'flex' === $property && str_contains($namespace, '\Tui\\') && null !== $value ? (int) $value : $value;
        }
        $name = (new \ReflectionClass($value))->getShortName();
        $target = $namespace.$name;
        if ($value instanceof \UnitEnum) {
            return \defined($target.'::'.$value->name) ? \constant($target.'::'.$value->name) : \constant($target.'::'.('VerticalAlign' === $name ? 'Top' : 'Left'));
        }
        if ('Color' === $name) {
            $sgr = $value->toForegroundCode();
            if (preg_match('/^\x1b\[(\d+)m$/D', $sgr, $match)) {
                return $target::named([30 => 'black', 31 => 'red', 32 => 'green', 33 => 'yellow', 34 => 'blue', 35 => 'magenta', 36 => 'cyan', 37 => 'white', 39 => 'default', 90 => 'bright_black', 91 => 'bright_red', 92 => 'bright_green', 93 => 'bright_yellow', 94 => 'bright_blue', 95 => 'bright_magenta', 96 => 'bright_cyan', 97 => 'bright_white'][(int) $match[1]]);
            }
            if (preg_match('/^\x1b\[38;5;(\d+)m$/D', $sgr, $match)) {
                return $target::palette((int) $match[1]);
            }

            return $target::hex($value->toHex());
        }
        if ('BorderPattern' === $name) {
            return new $target($value->getChars(), $value->getStrategies());
        }
        if ('Padding' === $name) {
            return new $target($value->top, $value->right, $value->bottom, $value->left);
        }
        if ('Border' === $name) {
            return new $target($value->top, $value->right, $value->bottom, $value->left, self::value($value->pattern, $namespace), self::value($value->color, $namespace));
        }

        return $value;
    }
}
