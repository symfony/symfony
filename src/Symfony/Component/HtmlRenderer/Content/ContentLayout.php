<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Content;

use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;

/**
 * Portable local drawing data, composed later within the containing element's painting order.
 *
 * @experimental
 */
final readonly class ContentLayout
{
    public DisplayList $displayList;

    public function __construct(public float $height, DisplayList $displayList, public bool $clipToBounds = true)
    {
        if (!is_finite($height) || $height < 0) {
            throw new InvalidArgumentException('Content height must be finite and non-negative.');
        }
        $states = ['clip' => 0, 'transform' => 0, 'opacity' => 0, 'filter' => 0];
        $operations = [];
        foreach ($displayList as $key => $operation) {
            $operation = self::validateOperation($operation);
            $type = $operation['type'];
            $operation = self::copyValues($operation);
            if ('text' === $type && (!\is_string($operation['text'] ?? null) || preg_match('/[^\P{Cc}\t\r\n]/u', $operation['text']) || 1 !== preg_match('//u', $operation['text']))) {
                throw new InvalidArgumentException('Content text must be valid UTF-8 without non-text control characters.');
            }
            foreach (['clip', 'transform', 'opacity', 'filter'] as $state) {
                if ('push-'.$state === $type) {
                    ++$states[$state];
                } elseif ('pop-'.$state === $type && --$states[$state] < 0) {
                    throw new InvalidArgumentException('Content drawing cannot restore outer state.');
                }
            }
            $operations[$key] = $operation;
        }
        if (array_filter($states)) {
            throw new InvalidArgumentException('Content drawing state must be balanced.');
        }
        $this->displayList = new DisplayList($operations);
    }

    /** @return array<array-key, mixed> */
    private static function validateOperation(mixed $operation): array
    {
        if (!\is_array($operation) || !\is_string($type = $operation['type'] ?? null) || '' === $type) {
            throw new InvalidArgumentException('A content drawing operation requires a type.');
        }

        return $operation;
    }

    private static function copyValues(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 64) {
            throw new InvalidArgumentException('Content drawing data is nested too deeply.');
        }
        if (\is_array($value)) {
            $copy = [];
            foreach ($value as $key => $item) {
                $copy[$key] = self::copyValues($item, $depth + 1);
            }

            return $copy;
        } elseif (\is_float($value) && !is_finite($value) || null !== $value && !\is_scalar($value)) {
            throw new InvalidArgumentException('Content drawing data must contain only finite scalar values and arrays.');
        }

        return $value;
    }
}
