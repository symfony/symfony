<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Fixtures;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

class LegacyResource
{
    /**
     * Builds an older resource payload without invoking its current constructor.
     *
     * @param class-string<SelfCheckingResourceInterface> $class
     *
     * @param array{resource: string, pattern?: string|null, loadedAt?: int|null, recent?: array<string, string>|null}|array{files: array<string, null>, className: string, excludedVendors: array<array-key, string>, hash: string} $properties
     */
    public static function create(string $class, array $properties): SelfCheckingResourceInterface
    {
        $data = '';
        foreach ($properties as $name => $value) {
            $data .= serialize($name).serialize($value);
        }

        return unserialize(\sprintf('O:%d:"%s":%d:{%s}', \strlen($class), $class, \count($properties), $data), ['allowed_classes' => [$class]]);
    }
}
