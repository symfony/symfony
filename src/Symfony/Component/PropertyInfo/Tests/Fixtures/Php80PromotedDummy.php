<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php80PromotedDummy
{
    public function __construct(private string $promoted)
    {
    }

    public function getPromoted(): string
    {
        return $this->promoted;
    }
}
