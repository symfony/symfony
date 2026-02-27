<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InitializedConstructor;

use Symfony\Component\ObjectMapper\Attribute\Map;

class D
{
    #[Map(if: false)]
    public string $barUpperCase;

    public function __construct(string $bar)
    {
        $this->barUpperCase = strtoupper($bar);
    }
}
