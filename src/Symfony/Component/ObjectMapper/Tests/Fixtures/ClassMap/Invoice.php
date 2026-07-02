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

final class Invoice
{
    public function __construct(
        public int $total,
        public string $currency,
    ) {
    }
}
