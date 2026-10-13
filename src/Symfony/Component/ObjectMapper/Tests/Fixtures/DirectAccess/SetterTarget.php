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

class SetterTarget
{
    public string $name = '';

    public function setName(string $name): void
    {
        $this->name = strtoupper($name);
    }
}
