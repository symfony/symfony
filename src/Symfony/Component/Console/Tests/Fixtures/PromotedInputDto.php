<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;

final class PromotedInputDto
{
    public function __construct(
        #[Argument]
        public string $name = 'Bob',
        #[Option]
        public int $count = 42,
    ) {
    }
}
