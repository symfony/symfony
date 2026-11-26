<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Event;

/** @experimental */
final class KeyboardEvent
{
    public function __construct(public readonly string $key, public readonly ?string $text = null, public readonly bool $shift = false, public readonly bool $control = false, public readonly bool $alt = false, public readonly bool $meta = false)
    {
    }
}
