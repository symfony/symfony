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

class C
{
    public string $bar;

    public function __construct(string $bar)
    {
        $this->bar = $bar;
    }
}
