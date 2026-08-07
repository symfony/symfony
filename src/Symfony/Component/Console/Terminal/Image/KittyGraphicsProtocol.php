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
    public const BEL = "\x07";

    public function detectPastedImage(string $data): bool
    {
        return str_contains($data, self::APC_START);
    }

    public function decode(string $data): array
    {
        if (false === $start = strpos($data, self::APC_START)) {
            return ['data' => '', 'format' => null];
        }

        $offset = $start;
        $payload = '';
        $format = null;

        // A single image is split into several APC sequences when its payload is
        // larger than the maximum chunk size; all of them but the last one carry
        // "m=1" in their control data.
        while (true) {
            // the sequence ends at whichever terminator comes first
            $st = strpos($data, self::ST, $offset);
            $bel = strpos($data, self::BEL, $offset);

            if (false === $end = match (true) {
                false === $st => $bel,
                false === $bel => $st,
                default => min($st, $bel),
            }) {
                return ['data' => '', 'format' => null];
            }

            $terminator = $end === $st ? self::ST : self::BEL;

            $content = substr($data, $offset + \strlen(self::APC_START), $end - $offset - \strlen(self::APC_START));

            if (false === $semicolonPos = strpos($content, ';')) {
                return ['data' => '', 'format' => null];
            }

            $controlData = substr($content, 0, $semicolonPos);
            $payload .= substr($content, $semicolonPos + 1);
            $format ??= $this->parseFormat($controlData);
            $offset = $end + \strlen($terminator);

            if ('1' !== $this->parseControlValue($controlData, 'm')) {
                break;
            }

            if (self::APC_START !== substr($data, $offset, \strlen(self::APC_START))) {
                // more chunks were announced but none follows: the image is incomplete
                return ['data' => '', 'format' => null];
            }
        }

        $decodedData = base64_decode($payload, true);
        if (false === $decodedData) {
            return ['data' => '', 'format' => null];
        }

        return ['data' => $decodedData, 'format' => $format];
    }

    public function encode(string $imageData, ?int $maxWidth = null): string
    {
        if ('png' !== $this->detectImageFormat($imageData)) {
            return '';
        }

        $controlParts = ['a=T', 'f=100'];

        if (null !== $maxWidth) {
            $controlParts[] = \sprintf('c=%d', $maxWidth);
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
        return match ($this->parseControlValue($controlData, 'f')) {
            '24' => 'rgb',
            '32' => 'rgba',
            '100' => 'png',
            default => null,
        };
    }

    private function parseControlValue(string $controlData, string $key): ?string
    {
        foreach (explode(',', $controlData) as $pair) {
            $parts = explode('=', $pair, 2);
            if (2 === \count($parts) && $key === $parts[0]) {
                return $parts[1];
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
