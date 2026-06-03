<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\IsNotNullCondition;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;

#[Map(IsNotNullTarget::class)]
class IsNotNullSource
{
    public function __construct(
        #[Map(if: new IsNotNull())]
        public ?string $name = null,
        #[Map(if: new IsNotNull())]
        public ?int $age = null,
    ) {
    }
}
