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

/**
 * @author Dmitrii <github.com/d-mitrofanov-v>
 */
class DummyWithUnion
{
    public function __construct(
        public int|float $value,
        public string|int $value2,
    ) {
    }
}
