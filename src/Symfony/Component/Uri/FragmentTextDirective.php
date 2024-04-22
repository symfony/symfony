<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri;

/**
 * As defined in the Scroll to Text Fragment proposal.
 *
 * @see https://wicg.github.io/scroll-to-text-fragment/
 *
 * @experimental
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class FragmentTextDirective implements \Stringable
{
    public function __construct(
        public string $start,
        public ?string $end = null,
        public ?string $prefix = null,
        public ?string $suffix = null,
    ) {
    }

    /**
     * Dash, comma and ampersand are encoded, @see https://wicg.github.io/scroll-to-text-fragment/#syntax.
     */
    public function __toString(): string
    {
        $encode = static fn (string $value) => strtr($value, ['-' => '%2D', ',' => '%2C', '&' => '%26']);

        return ':~:text='
            .($this->prefix ? $encode($this->prefix).'-,' : '')
            .$encode($this->start)
            .($this->end ? ','.$encode($this->end) : '')
            .($this->suffix ? ',-'.$encode($this->suffix) : '');
    }
}
