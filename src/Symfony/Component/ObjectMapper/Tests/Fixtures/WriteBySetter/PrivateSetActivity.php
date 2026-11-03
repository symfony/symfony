<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\WriteBySetter;

class PrivateSetActivity
{
    public private(set) string $name = '';

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
