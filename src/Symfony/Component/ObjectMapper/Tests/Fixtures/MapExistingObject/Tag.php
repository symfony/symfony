<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapExistingObject;

final class Tag
{
    public int $constructorCalls = 0;

    public function __construct(
        public string $name = '',
    ) {
        ++$this->constructorCalls;
    }
}
