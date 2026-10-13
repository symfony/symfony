<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

class PlainSource
{
    public array $tags = ['a', 'b'];
    public int $count;

    public function __construct(
        public string $name = 'jane',
        public ?string $nickname = null,
    ) {
    }
}
