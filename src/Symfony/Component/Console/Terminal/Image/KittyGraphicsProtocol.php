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
 * Handles the Kitty Graphics Protocol for terminal image paste/display.
 *
 * The Kitty Graphics Protocol uses Application Programming Command (APC) sequences:
 * - Start: ESC _ G (0x1B 0x5F 0x47)
 * - End: ESC \ (0x1B 0x5C) - also known as ST (String Terminator)
 *
 * Format: ESC_G<control data>;<payload>ESC\
 *
 * @see https://sw.kovidgoyal.net/kitty/graphics-protocol/
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class KittyGraphicsProtocol implements ImageProtocolInterface
{
    public const APC_START = "\x1b_G";
    public const ST = "\x1b\\";

    public function detectPastedImage(string $data): bool
    {
        return str_contains($data, self::APC_START);
    }

    public function decode(string $data): array
    {
        if (false === $start = strpos($data, self::APC_START)) {
            return ['data' => '', 'format' => null];
        }

        if (false === $end = strpos($data, self::ST, $start)) {
            $end = strpos($data, "\x07", $start);
        }

        if (false === $end) {
            return ['data' => '', 'format' => null];
        }

        $content = substr($data, $start + \strlen(self::APC_START), $end - $start - \strlen(self::APC_START));

        if (false === $semicolonPos = strpos($content, ';')) {
            return ['data' => '', 'format' => null];
        }

        $controlData = substr($content, 0, $semicolonPos);
        $payload = substr($content, $semicolonPos + 1);

        $decodedData = base64_decode($payload, true);
        if (false === $decodedData) {
            return ['data' => '', 'format' => null];
        }

        return ['data' => $decodedData, 'format' => $this->parseFormat($controlData)];
    }

    public function encode(string $imageData, ?int $maxWidth = null): string
    {
        $format = $this->detectImageFormat($imageData);

        $controlParts = ['a=T', 'f=100'];

        if (null !== $maxWidth) {
            $controlParts[] = \sprintf('c=%d', $maxWidth);
        }

        if ('png' === $format) {
            $controlParts[1] = 'f=100';
        }

        $controlData = implode(',', $controlParts);
        $payload = base64_encode($imageData);
        $maxChunkSize = 4096;

        if (\strlen($payload) <= $maxChunkSize) {
            return self::APC_START.$controlData.';'.$payload.self::ST;
        }

        $chunks = str_split($payload, $maxChunkSize);
        $result = '';

        foreach ($chunks as $i => $chunk) {
            $isLast = ($i === \count($chunks) - 1);
            $chunkControl = $i > 0 ? 'm='.($isLast ? '0' : '1') : $controlData.',m='.($isLast ? '0' : '1');
            $result .= self::APC_START.$chunkControl.';'.$chunk.self::ST;
        }

        return $result;
    }

    public function getName(): string
    {
        return 'kitty';
    }

    private function parseFormat(string $controlData): ?string
    {
        foreach (explode(',', $controlData) as $pair) {
            $parts = explode('=', $pair, 2);
            if (2 === \count($parts) && 'f' === $parts[0]) {
                return match ($parts[1]) {
                    '24' => 'rgb',
                    '32' => 'rgba',
                    '100' => 'png',
                    default => null,
                };
            }
        }

        return null;
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
