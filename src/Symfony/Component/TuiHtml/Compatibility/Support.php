<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Compatibility;

/**
 * How well an HTML or CSS feature is supported.
 *
 * Features are keyed like MDN browser-compat-data, for instance
 * "css.properties.border-radius" or "html.elements.select".
 *
 * @experimental
 */
final class Support
{
    public function __construct(public readonly SupportLevel $level, public readonly string $note = '')
    {
    }

    public static function supported(string $note = ''): self
    {
        return new self(SupportLevel::Supported, $note);
    }

    public static function partial(string $note): self
    {
        return new self(SupportLevel::Partial, $note);
    }

    public static function ignored(string $note = ''): self
    {
        return new self(SupportLevel::Ignored, $note);
    }

    /**
     * @return array{level: string, note?: string}
     */
    public function toArray(): array
    {
        return '' === $this->note ? ['level' => $this->level->value] : ['level' => $this->level->value, 'note' => $this->note];
    }
}
