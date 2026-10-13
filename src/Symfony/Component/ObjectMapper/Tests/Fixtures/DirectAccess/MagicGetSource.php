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

class MagicGetSource
{
    public string $name;

    public function __construct()
    {
        unset($this->name);
    }

    public function __get(string $property): string
    {
        return 'magic';
    }
}
