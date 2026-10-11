<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Replaces the {name} placeholders in the key of a controller attribute with the request attributes of the same name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class KeyPlaceholderResolver
{
    /**
     * @template T of object
     *
     * @param ControllerAttributeEvent<T, ControllerEvent|ControllerArgumentsEvent> $event
     */
    public static function resolve(string $key, ControllerAttributeEvent $event): string
    {
        $attributes = $event->kernelEvent->getRequest()->attributes;

        return preg_replace_callback('/\{([\w\x80-\xFF]++)\}/', static function (array $m) use ($attributes, $event): string {
            if (\is_string($value = $attributes->get($m[1])) || \is_int($value) || $value instanceof \Stringable) {
                return (string) $value;
            }

            $attribute = substr(strrchr('\\'.$event->attribute::class, '\\'), 1);
            $controller = self::getControllerName($event->kernelEvent->getController());

            if (!$attributes->has($m[1])) {
                throw new \LogicException(\sprintf('Cannot resolve the "%s" placeholder in the key of the "#[%s]" attribute of "%s()": the request has no "%s" attribute.', $m[0], $attribute, $controller, $m[1]));
            }

            throw new \TypeError(\sprintf('Cannot resolve the "%s" placeholder in the key of the "#[%s]" attribute of "%s()": the "%s" request attribute must be a string, an integer or a "Stringable" object, "%s" given.', $m[0], $attribute, $controller, $m[1], get_debug_type($value)));
        }, $key);
    }

    private static function getControllerName(callable $controller): string
    {
        $r = new \ReflectionFunction($controller(...));

        return !$r->isAnonymous() && ($class = $r->getClosureCalledClass()) ? $class->name.'::'.$r->name : $r->name;
    }
}
