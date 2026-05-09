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

class IsNotNullSourceMapping
{
    public function __construct(
        public ?string $firstName = null,
        public ?int $score = null,
    ) {
    }
}
