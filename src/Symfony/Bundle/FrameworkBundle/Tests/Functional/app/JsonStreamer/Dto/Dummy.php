<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonStreamer\Dto;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonStreamer\RangeToStringValueTransformer;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonStreamer\StringToRangeValueTransformer;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\Attribute\StreamedName;
use Symfony\Component\JsonStreamer\Attribute\ValueTransformer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
#[JsonStreamable]
class Dummy
{
    #[StreamedName('@name')]
    #[ValueTransformer(
        nativeToStream: 'strtoupper',
        streamToNative: 'strtolower',
    )]
    public string $name = 'dummy';

    #[ValueTransformer(
        nativeToStream: RangeToStringValueTransformer::class,
        streamToNative: StringToRangeValueTransformer::class,
    )]
    public array $range = [10, 20];
}
