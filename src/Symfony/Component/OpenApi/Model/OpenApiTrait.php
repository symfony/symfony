<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Model;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait OpenApiTrait
{
    public function toArray(): array
    {
        return [];
    }

    private function normalizeCollection(?array $items): ?array
    {
        if (null === $items) {
            return null;
        }

        $normalized = [];
        foreach ($items as $key => $item) {
            if ($item instanceof OpenApiModel) {
                $normalized[$key] = $item->toArray();
            } elseif ($item instanceof \BackedEnum) {
                $normalized[$key] = $item->value;
            } else {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
