<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Terminal\Image;

/**
 * Contract for terminal image protocol handlers.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
interface ImageProtocolInterface
{
    public function detectPastedImage(string $data): bool;

    /**
     * @return array{data: string, format: string|null}
     */
    public function decode(string $data): array;

    public function encode(string $imageData, ?int $maxWidth = null): string;

    public function getName(): string;
}
