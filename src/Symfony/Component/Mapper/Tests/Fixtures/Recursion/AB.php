<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests\Fixtures\Recursion;

use Symfony\Component\Mapper\Attributes\Map;

#[Map(Dto::class)]
class AB
{
    #[Map('dto')]
    public AB $ab;
}
