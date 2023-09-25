<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests\Fixtures;

class B
{
    public function __construct(private string $bar)
    {
    }
    public string $baz;
    public string $transform;
    public string $concat;
    public bool $nomap = true;
    public int $id;
    public D $relation;
    public D $relationNotMapped;
}
