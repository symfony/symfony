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

use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\TuiHtml\Render\RenderContext;

/** @internal */
final class ContextBridge
{
    /** @var \WeakMap<NativeContext, RenderContext>|null */
    private static ?\WeakMap $contexts = null;
    /** @var array<class-string, array<string, bool>> */
    private static array $computedParameters = [];

    public static function forWidget(\Symfony\Component\Tui\Widget\AbstractWidget $widget, RenderContext $context, string $method = 'render'): RenderContext|NativeContext
    {
        if (!isset(self::$computedParameters[$widget::class][$method])) {
            $parameters = (new \ReflectionMethod($widget, $method))->getParameters();
            $type = $parameters[array_key_last($parameters)]->getType();
            self::$computedParameters[$widget::class][$method] = $type instanceof \ReflectionUnionType && array_any($type->getTypes(), static fn (\ReflectionType $part): bool => $part instanceof \ReflectionNamedType && RenderContext::class === $part->getName());
        }

        return self::$computedParameters[$widget::class][$method] ? $context : self::native($context);
    }

    public static function native(RenderContext|NativeContext $context): NativeContext
    {
        if ($context instanceof NativeContext) {
            return $context;
        }
        $native = new NativeContext($context->getColumns(), $context->getRows(), StyleBridge::native($context->getStyle()), $context->getFontRegistry(), $context->shouldFillRows());
        self::$contexts ??= new \WeakMap();
        self::$contexts[$native] = $context;

        return $native;
    }

    public static function computed(NativeContext|RenderContext $context): RenderContext
    {
        if ($context instanceof RenderContext) {
            return $context;
        }
        if (null !== $cached = self::$contexts[$context] ?? null) {
            return $cached;
        }

        return new RenderContext($context->getColumns(), $context->getRows(), StyleBridge::computed($context->getStyle()), $context->getFontRegistry(), $context->shouldFillRows());
    }
}
