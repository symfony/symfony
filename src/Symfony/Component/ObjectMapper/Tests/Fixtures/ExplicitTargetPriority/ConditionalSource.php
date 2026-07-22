<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ExplicitTargetPriority;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Target::class)]
class ConditionalSource
{
    #[Map(target: 'email', if: [self::class, 'never'])]
    public string $customerEmail = 'from-customerEmail';

    public string $email = 'from-email';

    public static function never(mixed $value): bool
    {
        return false;
    }
}
