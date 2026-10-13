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

class TransformedTarget extends PlainTarget
{
    public static function create(): self
    {
        return new self();
    }

    public function setName(string $name): void
    {
        $this->name = strtoupper($name);
    }
}
