<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Uid\Uuid;

class DummyWithVariadicParameter
{
    public array $variadic;

    public function __construct(Uuid ...$variadic)
    {
        $this->variadic = $variadic;
    }
}
