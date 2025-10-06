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

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @author Dmitrii <github.com/d-mitrofanov-v>
 */
class DummyWithUnion
{
    public function __construct(
        #[SerializedName('@value')]
        public int|float $value,
        #[SerializedName('@value2')]
        public string|int $value2,
    ) {
    }
}
