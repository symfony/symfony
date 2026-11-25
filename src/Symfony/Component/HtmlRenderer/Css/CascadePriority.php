<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class CascadePriority
{
    public static function of(Declaration $declaration, int $specificity, int $order, string $layer, bool $userAgent, int $declarationIndex = 0, bool $scoped = false, int $context = 0): array
    {
        return [(int) $declaration->important, $declaration->important ? (int) $userAgent : ($userAgent ? 0 : ($order < 0 && 0 === $specificity ? 1 : 2)), (int) (\PHP_INT_MAX === $specificity), $declaration->important ? $context : -$context, $declaration->important ? strtr($layer, '0123456789', '9876543210') : $layer, $specificity, (int) $scoped, $order, $declarationIndex];
    }
}
