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
final readonly class KeyboardEvent
{
    public function __construct(public string $key, public ?string $text = null, public bool $shift = false, public bool $control = false, public bool $alt = false, public bool $meta = false)
    {
    }
}
