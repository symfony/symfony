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
 * Handles the iTerm2 Inline Images Protocol (OSC 1337).
 *
 * The iTerm2 protocol uses Operating System Command (OSC) sequences:
 * - Start: ESC ] 1337 ; File= (0x1B 0x5D 0x31 0x33 0x33 0x37 0x3B 0x46 0x69 0x6C 0x65 0x3D)
 * - End: BEL (0x07) or ESC \ (0x1B 0x5C)
 *
 * Format: ESC]1337;File=[arguments]:[base64 data]BEL
 *
 * @see https://iterm2.com/documentation-images.html
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class ITerm2Protocol implements ImageProtocolInterface
{
    public const OSC_START = "\x1b]1337;File=";
    public const BEL = "\x07";
    public const ST = "\x1b\\";

    public function detectPastedImage(string $data): bool
    {
        return str_contains($data, self::OSC_START);
    }

    public function decode(string $data): array
    {
        if (false === $start = strpos($data, self::OSC_START)) {
            return ['data' => '', 'format' => null];
        }

        if (false === $end = strpos($data, self::BEL, $start)) {
            $end = strpos($data, self::ST, $start);
        }

        if (false === $end) {
            return ['data' => '', 'format' => null];
        }

        $content = substr($data, $start + \strlen(self::OSC_START), $end - $start - \strlen(self::OSC_START));

        if (false === $colonPos = strpos($content, ':')) {
            return ['data' => '', 'format' => null];
        }

        $payload = substr($content, $colonPos + 1);

        if (false === $decodedData = base64_decode($payload, true)) {
            return ['data' => '', 'format' => null];
        }

        $format = $this->detectImageFormat($decodedData);

        return ['data' => $decodedData, 'format' => $format];
    }

    public function encode(string $imageData, ?int $maxWidth = null): string
    {
        $arguments = [
            'inline=1',
        ];

        if ($maxWidth) {
            $arguments[] = \sprintf('width=%d', $maxWidth);
        }

        $arguments[] = 'preserveAspectRatio=1';
        $argumentString = implode(';', $arguments);
        $payload = base64_encode($imageData);

        return self::OSC_START.$argumentString.':'.$payload.self::BEL;
    }

    public function getName(): string
    {
        return 'iterm2';
    }

    private function detectImageFormat(string $data): ?string
    {
        return match (true) {
            str_starts_with($data, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($data, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($data, 'GIF87a'), str_starts_with($data, 'GIF89a') => 'gif',
            str_starts_with($data, 'RIFF') && 'WEBP' === substr($data, 8, 4) => 'webp',
            default => null,
        };
    }
}
