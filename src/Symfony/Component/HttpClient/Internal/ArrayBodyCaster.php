<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\HttpClient\Response\StreamableInterface;

/**
 * Casts the values of an array body the same way for all clients and decorators.
 *
 * @internal
 */
final class ArrayBodyCaster
{
    /**
     * Turns objects into arrays of their properties or into strings, and streams into what $onStream returns.
     *
     * @param \Closure(resource): mixed $onStream Called with each stream, including the ones created from StreamableInterface values
     */
    public static function cast(array $body, \Closure $onStream): array
    {
        array_walk_recursive($body, static function (&$v) use ($onStream) {
            if (\is_resource($v) || $v instanceof StreamableInterface) {
                $v = $onStream($v instanceof StreamableInterface ? $v->toStream(false) : $v);
            } elseif (\is_object($v)) {
                if ($vars = get_object_vars($v)) {
                    $v = self::cast($vars, $onStream);
                } elseif ($v instanceof \Stringable) {
                    $v = (string) $v;
                } else {
                    $v = [];
                }
            }
        });

        return $body;
    }
}
