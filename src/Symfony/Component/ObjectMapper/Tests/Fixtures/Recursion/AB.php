<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\Recursion;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(Dto::class)]
class AB
{
    #[Map('dto')]
    public AB $ab;
}
