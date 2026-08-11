<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

/**
 * @experimental
 *
 * @internal
 */
final class LineBufferDiffer
{
    /**
     * @return array{first: int, last: int}|null
     */
    public function findChangedRange(LineBufferInterface $current, LineBufferInterface $previous): ?array
    {
        if ($current === $previous) {
            return null;
        }

        $currentCount = \count($current);
        $previousCount = \count($previous);
        $commonPrefix = $this->commonPrefixLength($current, $previous, min($currentCount, $previousCount));

        if ($currentCount === $previousCount) {
            if ($commonPrefix === $currentCount) {
                return null;
            }

            $commonSuffix = $this->commonSuffixLength($current, $previous, $currentCount - $commonPrefix);

            return ['first' => $commonPrefix, 'last' => $currentCount - $commonSuffix - 1];
        }

        return ['first' => $commonPrefix, 'last' => max($currentCount, $previousCount) - 1];
    }

    private function commonPrefixLength(LineBufferInterface $current, LineBufferInterface $previous, int $limit): int
    {
        if (0 === $limit) {
            return 0;
        }
        if ($current === $previous) {
            return min($limit, \count($current));
        }

        if ($current instanceof ConcatenatedLineBuffer && $previous instanceof ConcatenatedLineBuffer) {
            $currentBuffers = $current->getBuffers();
            $previousBuffers = $previous->getBuffers();
            $matched = 0;
            $bufferCount = min(\count($currentBuffers), \count($previousBuffers));

            for ($i = 0; $i < $bufferCount && $matched < $limit; ++$i) {
                $currentBuffer = $currentBuffers[$i];
                $previousBuffer = $previousBuffers[$i];
                $bufferLimit = min($limit - $matched, \count($currentBuffer), \count($previousBuffer));
                $bufferMatched = $this->commonPrefixLength($currentBuffer, $previousBuffer, $bufferLimit);
                $matched += $bufferMatched;

                if ($bufferMatched < $bufferLimit || \count($currentBuffer) !== \count($previousBuffer)) {
                    return $matched;
                }
            }

            return $matched;
        }

        for ($i = 0; $i < $limit; ++$i) {
            if ($current->getLine($i) !== $previous->getLine($i)) {
                return $i;
            }
        }

        return $limit;
    }

    private function commonSuffixLength(LineBufferInterface $current, LineBufferInterface $previous, int $limit): int
    {
        if (0 === $limit) {
            return 0;
        }
        if ($current === $previous) {
            return min($limit, \count($current));
        }

        if ($current instanceof ConcatenatedLineBuffer && $previous instanceof ConcatenatedLineBuffer) {
            $currentBuffers = $current->getBuffers();
            $previousBuffers = $previous->getBuffers();
            $matched = 0;
            $currentIndex = \count($currentBuffers) - 1;
            $previousIndex = \count($previousBuffers) - 1;

            while ($currentIndex >= 0 && $previousIndex >= 0 && $matched < $limit) {
                $currentBuffer = $currentBuffers[$currentIndex--];
                $previousBuffer = $previousBuffers[$previousIndex--];
                $bufferLimit = min($limit - $matched, \count($currentBuffer), \count($previousBuffer));
                $bufferMatched = $this->commonSuffixLength($currentBuffer, $previousBuffer, $bufferLimit);
                $matched += $bufferMatched;

                if ($bufferMatched < $bufferLimit || \count($currentBuffer) !== \count($previousBuffer)) {
                    return $matched;
                }
            }

            return $matched;
        }

        $currentCount = \count($current);
        $previousCount = \count($previous);
        for ($i = 1; $i <= $limit; ++$i) {
            if ($current->getLine($currentCount - $i) !== $previous->getLine($previousCount - $i)) {
                return $i - 1;
            }
        }

        return $limit;
    }
}
