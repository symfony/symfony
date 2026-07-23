<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: BasePayment::class, transform: [self::class, 'fromPayment'])]
final class BasePaymentView
{
    public function __construct(
        public readonly int $amountCents,
    ) {
    }

    public static function fromPayment(mixed $value, object $source, ?object $target): self
    {
        return new self($source->amount->cents);
    }
}
