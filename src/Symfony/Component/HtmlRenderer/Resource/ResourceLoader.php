<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Resource;

final class ResourceLoader
{
    public const MAX_BYTES = 8388608;

    public function __construct(private readonly ?\Closure $loader = null, private readonly int $maxBytes = self::MAX_BYTES)
    {
        if ($maxBytes < 1) {
            throw new \InvalidArgumentException('The resource size limit must be positive.');
        }
    }

    public function load(string $source): ?string
    {
        if ('' === $source) {
            return null;
        }
        if (preg_match('#^data:([^,]*?)(;base64)?,(.*)$#is', $source, $match)) {
            if (\strlen($match[3]) > $this->maxBytes * 4 / 3 + 4) {
                return null;
            }
            $data = '' !== $match[2] ? base64_decode(preg_replace('/\s+/', '', $match[3]) ?? '', true) : rawurldecode($match[3]);

            return false === $data || '' === $data || \strlen($data) > $this->maxBytes ? null : $data;
        }
        if (null === $this->loader) {
            return null;
        }
        try {
            $data = ($this->loader)($source);
        } catch (\Throwable) {
            return null;
        }

        return !\is_string($data) || '' === $data || \strlen($data) > $this->maxBytes ? null : $data;
    }
}
