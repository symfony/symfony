<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\SkippedPromotedMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

class MappedThenRejectedTarget
{
    public function __construct(
        #[Map(source: 'one')]
        #[Map(source: 'two', if: [self::class, 'never'])]
        public string $value = 'default',
    ) {
    }

    public static function never(mixed $value): bool
    {
        return false;
    }
}
