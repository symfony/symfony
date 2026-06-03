<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Mapping;

use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\TypeInfo\Type;

final class SyntheticPropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function load(string $className, array $options = [], array $context = []): array
    {
        return [
            'synthetic' => PropertyMetadata::createSynthetic(Type::true(), [
                self::true(...),
            ]),
        ];
    }

    public static function true(): true
    {
        return true;
    }
}
