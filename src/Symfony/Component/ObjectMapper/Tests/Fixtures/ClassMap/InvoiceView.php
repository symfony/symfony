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

#[Map(source: Invoice::class, if: [self::class, 'isEuro'], transform: [self::class, 'fromEuro'])]
#[Map(source: Invoice::class, if: [self::class, 'isDollar'], transform: [self::class, 'fromDollar'])]
final class InvoiceView
{
    public function __construct(
        public readonly string $label,
    ) {
    }

    public static function isEuro(mixed $value, object $source, ?object $target): bool
    {
        return 'EUR' === $source->currency;
    }

    public static function isDollar(mixed $value, object $source, ?object $target): bool
    {
        return 'USD' === $source->currency;
    }

    public static function fromEuro(mixed $value, object $source, ?object $target): self
    {
        return new self($source->total.' EUR');
    }

    public static function fromDollar(mixed $value, object $source, ?object $target): self
    {
        return new self($source->total.' USD');
    }
}
